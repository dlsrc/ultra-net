<?php declare(strict_types=1);
/**
 * (c) 2005-2024 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra net package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Net;

enum DomainStatus {
	/**
	 * Строка является абсолютно валидным именем домена.
	 * DNS запись о домене существует.
	 */
	case Ok;
		
	/**
	 * Строка не соответствует требованиям к адресу электронной почты.
	 */
	case InvalidDomainName;
	
	/**
	 * Строка полностью соответствует требованиям к доменному имени,
	 * но домен, указанный в адресе, не существует либо недостижим.
	 */
	case DomainNotExists;
}
