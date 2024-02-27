<?php declare(strict_types=1);
/**
 * (c) 2005-2024 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra net package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Net;

readonly final class DomainName {
	/**
	 * Статус адреса
	 */
	public DomainStatus $status;

	/**
	 * Строка доменного имени
	 */
	public string $domain;

	/**
	 * IP-адрес домена
	 */
	public string $ip;

	public function __construct(string $domain) {
		if (!filter_var($domain, \FILTER_VALIDATE_DOMAIN, 	\FILTER_FLAG_HOSTNAME)) {
			$this->status = DomainStatus::InvalidDomainName;
			$this->domain = '';
			$this->ip     = '0.0.0.0';
			return;
		}

		$this->domain = $domain;
		$ip = gethostbyname($this->domain);

		if ($this->domain == $ip) {
			$this->status = DomainStatus::DomainNotExists;
			$this->ip     = '0.0.0.0';
			return;
		}

		$this->status = DomainStatus::Ok;
		$this->ip = $ip;
	}
}
