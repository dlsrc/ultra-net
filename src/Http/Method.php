<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra net package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Net\Http;

enum Method: string {
	case Get     = 'GET';
	case Head    = 'HEAD';
	case Post    = 'POST';
	case Put     = 'PUT';
	case Patch   = 'PATCH';
	case Delete  = 'DELETE';
	case Connect = 'CONNECT';
	case Options = 'OPTIONS';
	case Trace   = 'TRACE';
}
