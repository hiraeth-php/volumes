<?php

namespace Hiraeth\Volumes\Visibility;

use Hiraeth;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

/**
 * {@inheritDoc}
 */
class UmaskConverterDelegate implements Hiraeth\Delegate
{
	/**
	 * {@inheritDoc}
	 */
	static public function getClass(): string
	{
		return UmaskConverter::class;
	}


	/**
	 * {@inheritDoc}
	 */
	public function __invoke(Hiraeth\Application $app): object
	{
		return PortableVisibilityConverter::fromArray([
			'permissions' => [
				'file' => [
					'public'  => 0666 ^ umask(),
					'private' => 0660 ^ umask()
				],
				'dir' => [
					'public'  => 0777 ^ umask(),
					'private' => 0770 ^ umask()
				]
			]
		]);
	}
}
