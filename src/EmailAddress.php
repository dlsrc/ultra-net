<?php declare(strict_types=1);
/**
 * (c) 2005-2024 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra net package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Net;

readonly final class EmailAddress {
	/**
	 * Статус адреса
	 */
	public EmailStatus $status;

	/**
	 * Строка адреса поступившая в качестве аргумента при создании объекта.
	 * Например, "<my.email@example.com>" или "@D#$SS#$CDS5^^%&Cd"
	 */
	public string $raw;

	/**
	 * Чистая строка
	 */
	public string $email;

	/**
	 * Строка доменного имени
	 */
	public string $domain;

	/**
	 * IP-адрес домена
	 */
	public string $ip;

	/**
	 * Флаг, указывающий на возможность использовать адрес
	 * для отправки на него почты.
	 */
	public bool $valid;

	public function __construct(string $email, bool $unicode = false) {
		$this->raw = $email;
		$email = filter_var($email, \FILTER_SANITIZE_EMAIL);

		if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
			$this->email  = '';
			$this->status = EmailStatus::InvalidEmailString;
			$this->valid  = false;
			$this->domain = '';
			$this->ip     = '0.0.0.0';
			return;
		}

		$this->email  = $email;

        [, $this->domain] = explode('@', $this->email);
		$ip = gethostbyname($this->domain);

		if ($this->domain == $ip) {
			$this->status = EmailStatus::InvalidDomainName;
			$this->valid  = false;
			$this->ip     = '0.0.0.0';
			return;
		}

		$this->ip = $ip;

		if (!checkdnsrr($this->domain, 'MX')) {
			$this->status = EmailStatus::MXRecordMissing;
			$this->valid  = false;
			return;
		}

		if ($this->raw == $this->email) {
			$this->status = EmailStatus::Ok;
		}
		else {
			$this->status = EmailStatus::OkButRaw;
		}

		$this->valid = true;
	}
}
