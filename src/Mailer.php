<?php declare(strict_types=1);
/**
 * (c) 2005-2024 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra net package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Net;

use Ultra\Error;
use Ultra\Status;

class Mailer {
	public const string EOL   = "\r\n";
	public const string TEXT  = 'text/plain';
	public const string HTML  = 'text/html';
	private const array ALIAS = [
		'ContentType'               => 'content',
		'Subject'                   => 'title',
		'ReplyTo'                   => 'reply',
		'ReturnPath'                => 'path',
		'ReturnReceiptTo'           => 'receipt',
		'DispositionNotificationTo' => 'disp',
	];

	/**
	 * Адрес отправителя письма. Заголовок From должен быть обязательно указан и
	 * являться валидным адресом электронной почты, иначе письмо отправлено не будет.
	 */
	protected string $from;

	/**
	 * Адрес или список адресов доставки письма.
	 */ 
	protected string $to;

	/**
	 * Заголовки письма
	 */
	protected array $headers;

	/**
	 * Флаг разрешающий отправку письма, в случае если не все адреса прошли проверку.
	 * Письмо будет отправлено, если остался адрес From и хотя-бы один валидный адрес To.
	 */
	protected bool $incomplete;

	/**
	 * Количество адресов не прошедших проверку
	 */
	protected int $errors;

	/**
	 * Флаг разрешения указывать список Сc в качестве списка To, если список To оказался пуст.
	 */
	protected bool $cc_as_to;

	/**
	 * Итоговая сводка по адресам не прошедшим проверку
	 */
	protected array $summary;

	public static function text(string $from, string $to, string $message, string $subject = ''): bool {
		$mail = new Mailer($from, $to);
		return $mail->send($message, $subject);
	}

	public static function make(string $to = '', string $from = '', bool $incomplete = false, bool $cc_as_to = false): self {
		return new Mailer($from, $to, $incomplete, $cc_as_to);
	}

	public function __construct(string $from = '', string $to = '', bool $incomplete = false, bool $cc_as_to = false) {
		$this->errors     = 0;
		$this->summary    = [];
		$this->from       = $from ? $this->_add('From', $from) : '';
		$this->to         = $to ? $this->_add('To', $to) : '';
		$this->incomplete = $incomplete;
		$this->cc_as_to   = $cc_as_to;

		$this->headers = [
			'ContentType'               => self::TEXT,
			'charset'                   => 'utf-8',
			'Sender'                    => '',
			'To'                        => '',
			'Cc'                        => '',
			'Bcc'                       => '',
			'ReplyTo'                   => '',
			'Subject'                   => '',
			'ReturnPath'                => '',
			'ReturnReceiptTo'           => '',
			'DispositionNotificationTo' => '',
		];
	}

	public function __set(string $name, string $value): void {
		$lower = strtolower($name);

		if (!in_array($lower, [
			'charset',
			self::ALIAS['ContentType'],
			self::ALIAS['ReplyTo'],
			self::ALIAS['ReturnPath'],
			self::ALIAS['ReturnReceiptTo'],
			self::ALIAS['DispositionNotificationTo'],
			self::ALIAS['Subject'],
		])) {
			$name = ucfirst($name);
		}

		if (in_array($name, ['ContentType', 'charset', 'Subject'])) {
			$this->headers[$name] = $value;
			return;
		}

		switch ($lower) {
		case 'from':
			$this->from = $this->_add(ucfirst($lower), $value);
			return;

		case 'to':
			$this->to = $this->_add(ucfirst($lower), $value);
			return;

		case self::ALIAS['Subject']:
			$this->headers['Subject'] = $value;
			return;

		case self::ALIAS['ContentType']:
			$this->headers['ContentType'] = $value;
			return;

		case self::ALIAS['ReplyTo']:
			$name = 'ReplyTo';
			break;

		case self::ALIAS['ReturnPath']:
			$name = 'ReturnPath';
			break;

		case self::ALIAS['ReturnReceiptTo']:
			$name = 'ReturnReceiptTo';
			break;

		case self::ALIAS['DispositionNotificationTo']:
			$name = 'DispositionNotificationTo';
			break;
		}

		if (isset($this->headers[$name])) {
			$this->headers[$name] = $this->_add($name, $value);
		}
	}

	private function _add(string $header, string $email): string {
		if (in_array(strtolower($header), ['to', 'cc', 'bcc'])) {
			$addrs = preg_split('/[\s,]+/', $email);
		}
		else {
			$addrs = [$email];
		}

		foreach ($addrs as $key => $val) {
			$addr = new EmailAddress($val);

			if (!$addr->valid) {
				$this->summary[$header][] = $addr;
				$this->errors++;
				unset($addrs[$key]);
			}
			else {
				$addrs[$key] = $addr->email;
			}
		}

		if (empty($addrs)) {
			return '';
		}

		return implode(',', $addrs);
	}

	public static function prepare(string $str): string|null {
		$email = preg_split('/[\s,]+/', $str);

		foreach ($email as $key => $val) {
			if (!self::isEmail($val)) {
				unset($email[$key]);
			}
		}

		if (sizeof($email) > 0) {
			return implode(',', $email);
		}

		return null;
	}

	/*
	* Проверка написания почтового адреса
	*/
	public static function isEmail(string $mail): bool {
		return (new EmailAddress($mail))->valid;
	}

	/*
	* Получение доменного имени из строки письма
	*/
	public static function getDomain(string $str): string {
		$str = preg_replace('/^<?([^<>]+)>?$/', '\\1', $str);

		if (str_contains($str, '@')) {
			$addr = explode('@', $str);
			return $addr[1];
		}

		return $str;
	}

	/*
	* Проверка существования почтового сервера
	*/
	public static function isServer(string $str): int {
		$domain = new DomainName($str);

		return match($domain->status) {
			DomainStatus::Ok => $domain->isDnsRecord('MX') ? 0 : 2,
			DomainStatus::InvalidDomainName, DomainStatus::DomainNotExists => 1,
		};
	}

	public static function isAddress(string $mail): int {
		return match((new EmailAddress($mail))->status) {
			EmailStatus::InvalidDomainName  => 1,
			EmailStatus::MXRecordMissing    => 2,
			EmailStatus::InvalidEmailString => 3,
			default                         => 0,
		};
	}

	public static function isASCII(string $str): bool {
		if (preg_match('[^[\\040-\\176]+$]', $str) > 0) {
			return true;
		}

		return false;
	}

	public function sendIncomplete(): void {
		$this->incomplete = true;
	}

	public function useCcAsTo(): void {
		$this->cc_as_to = true;
	}

	public function readyToSend(): bool {
		if ('' == $this->from) {
			return false;
		}

		if ('' == $this->to && (!$this->cc_as_to || '' == $this->headers['Cc'])) {
			return false;
		}

		if (!$this->incomplete || $this->errors > 0) {
			return false;
		}

		return true;
	}

	public function isComplete(): bool {
		return 0 == $this->errors;
	}

	public function errors(): int {
		return $this->errors;
	}

	public function summary(): array {
		return $this->summary;
	}

	public function send(string $message, string $subject='', bool $ereg = false): bool {
		if (!$this->readyToSend()) {
			return false;
		}

		if ('' == $this->to) {
			$this->to = $this->headers['Cc'];
			$this->headers['Cc'] = '';
		}

		if ('' == $subject) {
			if ('' != $this->headers['Subject']) {
				$subject = $this->headers['Subject'];
			}
			else {
				$subject = 'no subject';
			}
		}

		if (!self::isASCII($subject)) {
			$subject = '=?'.$this->headers['charset'].'?B?'.base64_encode($subject).'?=';
		}

		if ($ereg) {
			if (!mail($this->to, $subject, $message, $this->getHeaders())) {
				Error::log('Email to '.$this->to.' was not sent.', Status::User);
				return false;
			}
			else {
				return true;
			}
		}
		else {
			return mb_send_mail($this->to, $subject, $message, $this->getHeaders());
		}
	}

	protected function getHeaders(): string {
		$headers = 'MIME-Version: 1.0'.self::EOL;
		$headers.= 'Content-Type: '.$this->headers['ContentType'].
		'; charset='.$this->headers['charset'].self::EOL;
		$headers.= 'From: '.$this->from.self::EOL;

		if ('' != $this->headers['Sender']) {
			$headers.= 'Sender: '.$this->headers['Sender'].self::EOL;
		}

		if ('' != $this->headers['Cc']) {
			$headers.= 'Cc: '.$this->headers['Cc'].self::EOL;
		}

		if ('' != $this->headers['Bcc']) {
			$headers.= 'Bcc: '.$this->headers['Bcc'].self::EOL;
		}

		if ('' != $this->headers['ReplyTo']) {
			$headers.= 'Reply-To: '.$this->headers['ReplyTo'].self::EOL;
		}

		if ('' != $this->headers['ReturnPath']) {
			$headers.= 'Return-Path: '.$this->headers['ReturnPath'].self::EOL;
		}

		if ('' != $this->headers['ReturnReceiptTo']) {
			$headers.= 'Return-Receipt-To: '.$this->headers['ReturnReceiptTo'].self::EOL;
		}

		if ('' != $this->headers['DispositionNotificationTo']) {
			$headers.= 'Disposition-Notification-To: '.$this->headers['DispositionNotificationTo'].self::EOL;
		}

		return $headers;
	}
}
