<?php

namespace Hiraeth\Volumes;

use Elazar\Flystream;
use Elazar\Flystream\ServiceLocator;
use Elazar\Flystream\FilesystemRegistry;
use League\Flysystem\FilesystemOperator;

/**
 *
 */
class StreamWrapper extends Flystream\StreamWrapper
{
	/**
	 * @var FilesystemRegistry
	 */
	static protected $registry;


	/**
	 *
	 */
	static public function getRegistry()
	{
		if (!isset(static::$registry)) {
			static::$registry = ServiceLocator::get(FilesystemRegistry::class);
		}

		return static::$registry;
	}


	/**
	 *
	 */
	static public function getProtocol(string $scheme, string $name = '')
	{
		return sprintf('%s-%s', $scheme, $name);
	}


	/**
	 * @param array<string, mixed[]> $config
	 */
	static public function register(string $scheme, string $name, FilesystemOperator $filesystem): void
	{
		static::getRegistry()->register(static::getProtocol($scheme, $name), $filesystem);
	}


	/**
	 *
	 */
	static public function unregister(string $scheme, string $name): void
	{
		static::getRegistry()->unregister(static::getProtocol($scheme, $name));
	}


	/**
	 *
	 */
	public static function unregisterAll(string $scheme): void
	{
		foreach (stream_get_wrappers() as $protocol) {
			if (strpos($protocol, static::getProtocol($scheme)) === 0) {
				static::getRegistry()->unregister($protocol);
			}
		}

		stream_wrapper_unregister($scheme);
	}


	/**
	 * {@inheritDoc}
	 */
	public function dir_opendir(string $path, int $options): bool
	{
		return parent::dir_opendir($this->getPath($path), $options);
	}


	/**
	 * {@inheritDoc}
	 */
	public function mkdir(string $path, int $mode, int $options): bool
	{
		return parent::mkdir($this->getPath($path), $mode, $options);
	}


	/**
	 * {@inheritDoc}
	 */
	public function rename(string $path_from, string $path_to): bool
	{
		return parent::rename($this->getPath($path_from), $this->getPath($path_to));
	}


	/**
	 * {@inheritDoc}
	 */
	public function rmdir(string $path, int $options): bool
	{
		return parent::rmdir($this->getPath($path), $options);
	}


	/**
	 * {@inheritDoc}
	 */
	public function stream_metadata(string $path, int $option, $value): bool
	{
		return parent::stream_metadata($this->getPath($path), $option, $value);
	}

	/**
	 * {@inheritDoc}
	 */
	public function stream_open(string $path, string $mode, int $options, ?string &$opened_path = null): bool
	{
		return parent::stream_open($this->getPath($path), $mode, $options, $opened_path);
	}

	/**
	 * {@inheritDoc}
	 */
	public function unlink(string $path): bool
	{
		return parent::unlink($this->getPath($path));
	}


	/**
	 * {@inheritDoc}
	 */
	public function url_stat(string $path, int $flags)
	{
		return parent::url_stat($this->getPath($path), $flags);
	}


	/**
	 * Determine the target from the URI's path component <scheme>://<volume>/<target>
	 */
	protected function getPath(string $path): string
	{
		$scheme = parse_url($path, PHP_URL_SCHEME);
		$name   = parse_url($path, PHP_URL_HOST);
		$path   = parse_url($path, PHP_URL_PATH);

		return sprintf('%s://%s', static::getProtocol($scheme, $name), ltrim($path, '/'));
	}
}
