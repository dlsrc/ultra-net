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
	public const string EOL  = "\r\n";
	public const string TEXT = 'text/plain';
	public const string HTML = 'text/html';

	protected EmailAddress $to;
	protected array $headers;

	protected function __construct(string $to = '') {
		$this->to = new EmailAddress($to);
		$this->headers = [
			'bcc'     => '',
			'cc'      => '',
			'charset' => 'utf-8',
			'content' => self::TEXT,
			'from'    => '',
			'reply'   => '',
			'rpath'   => '',
			'to'      => ''
		];
	}

	public function __set(string $name, string $value): void {
		if (isset($this->headers[$name])) {
			$this->headers[$name] = $value;
		}
	}

	public function send(string $message, string $subject='[Без темы]', bool $ereg = false): bool {
		if (!$this->to->valid) {
			return false;
		}

		if (!self::isASCII($subject)) {
			$subject = '=?'.$this->headers['charset'].'?B?'.\base64_encode($subject).'?=';
		}

		if ($ereg) {
			if (!\mail($this->to, $subject, $message, $this->getHeaders())) {
				Error::log(Error::message('e_sendmail', $subject, $this->to->email), Status::User);
				return false;
			}
			else {
				return true;
			}
		}
		else {
			return \mb_send_mail($this->to, $subject, $message, $this->getHeaders());
		}
	}

	protected function getHeaders(): string {
		$headers = 'MIME-Version: 1.0'.self::EOL;
		$headers.= 'Content-type: '.$this->headers['content'].
		'; charset='.$this->headers['charset'].self::EOL;

		if ('' != $this->headers['cc']) {
			if ($email = self::prepare($this->headers['cc'])) {
				$headers.= 'Cc: '.$email.self::EOL;
			}
		}

		if ('' != $this->headers['bcc']) {
			if ($email = self::prepare($this->headers['bcc'])) {
				$headers.= 'Bcc: '.$email.self::EOL;
			}
		}

		if ('' != $this->headers['from']) {
			if ($email = self::prepare($this->headers['from'])) {
				$headers.= 'From: '.$email.self::EOL;
			}
		}

		if ('' != $this->headers['reply']) {
			if ($email = self::prepare($this->headers['reply'])) {
				$headers.= 'Reply-To: '.$email.self::EOL;
			}
		}

		if ('' != $this->headers['rpath']) {
			if ($email = self::prepare($this->headers['rpath'])) {
				$headers.= 'Return-Path: '.$email.self::EOL;
			}
		}

		return $headers;
	}

	public static function prepare(string $str): ?string {
		$email = \preg_split('/[\s,]+/', $str);

		foreach ($email as $key => $val) {
			if (!self::isEmail($val)) {
				unset($email[$key]);
			}
		}

		if (\sizeof($email) > 0) {
			return \implode(',', $email);
		}

		return NULL;
	}

	public static function make(string $to, string $bcc = ''): self|null {
		if (!$email = self::prepare($to)) {
			return NULL;
		}

		if ('' != $bcc) {
			$mailer = new Mailer;
			$mailer->bcc = $email;
			return $mailer;
		}

		return new Mailer($email);
	}

	/*
	* Проверка написания почтового адреса
	*/
	public static function isEmail(string $mail): bool {
		if (\preg_match(
			'/^
				(<?)[a-zA-Z\d][\w\.\-]*[a-zA-Z\d]
				@
				(?:[a-zA-Z\d][a-zA-Z\d]?[\w\.\-]*)?[a-zA-Z\d]{2,}\.[a-zA-Z]{2,10}(>?)
			$/isx',
			$mail,
			$match
		) > 0) {
			if (('' == $match[1] && '' == $match[2]) || ('<' == $match[1] && '>' == $match[2])) {
				return true;
			}
		}

		return false;
	}

	/*
	* Получение доменного имени из строки письма
	*/
	public static function getDomain(string $str): string {
		$str = \preg_replace('/^<?([^<>]+)>?$/', '\\1', $str);

		if (\str_contains($str, '@')) {
			$addr = \explode('@', $str);
			return $addr[1];
		}

		return $str;
	}

	/*
	* Проверка существования почтового сервера
	*/
	public static function isServer(string $str): int {
		$server = self::getDomain($str);

		if ($server == \gethostbyname($server)) {
			return 1;
		}

		if (!\checkdnsrr($server, 'MX')) {
			return 2;
		}

		return 0;
	}

	public static function isAddress(string $mail): int {
		if (!self::isEmail($mail)) {
			return 3;
		}

		return self::isServer($mail);
	}

	public static function isASCII(string $str): bool {
		if (\preg_match('[^[\\040-\\176]+$]', $str) > 0) {
			return true;
		}

		return false;
	}
}
