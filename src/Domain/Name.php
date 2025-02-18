<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra net package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Net\Domain;

readonly final class Name {
	/**
	 * Статус адреса
	 */
	public Status $status;

	/**
	 * Строка доменного имени
	 */
	public string $domain;

	/**
	 * IP-адрес домена
	 */
	public string $ip;

	/**
	 * Флаг валидного домена
	 */
	public bool $valid;

	public function __construct(string $domain) {
		if (!filter_var($domain, \FILTER_VALIDATE_DOMAIN, 	\FILTER_FLAG_HOSTNAME)) {
			$this->status = Status::InvalidDomainName;
			$this->domain = '';
			$this->ip     = '0.0.0.0';
			$this->valid  = false;
			return;
		}

		$this->domain = $domain;
		$ip = gethostbyname($this->domain);

		if ($this->domain == $ip) {
			$this->status = Status::DomainNotExists;
			$this->ip     = '0.0.0.0';
			$this->valid  = false;
			return;
		}

		$this->status = Status::Ok;
		$this->ip     = $ip;
		$this->valid  = true;
	}

	/**
	 * Проверить наличие DNS записи
	 */
	public function isDnsRecord(string $type): bool {
		return $this->valid ? checkdnsrr($this->domain, $type) : false;
	}
}
