<?php

namespace Hiraeth\Volumes;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Elazar\Flystream;
use RuntimeException;

;

/**
 *
 */
class StreamWrapper extends Flystream\StreamWrapper
{
	/**
	 * @var array<string, mixed[]>
	 */
	static protected $configs = array();


	/**
	 * @var array<string, FilesystemOperator[]>
	 */
	static protected $filesystems = array();


	/**
	 * @param array<string, mixed[]> $config
	 */
	static public function register(string $scheme, string $name, FilesystemOperator $filesystem, array $config = array()): void
	{
		if (!isset(static::$filesystems[$scheme])) {
			static::$filesystems[$scheme] = array();
			static::$configs[$scheme] = array();

			stream_wrapper_register($scheme, static::class);
		}

		static::$filesystems[$scheme][$name] = $filesystem;
		static::$configs[$scheme][$name]     = $config;
	}


	/**
	 *
	 */
	static public function unregister(string $scheme, string $name): void
	{
		unset(static::$filesystems[$scheme][$name]);
		unset(static::$configs[$scheme][$name]);
	}


	/**
	 *
	 */
	public static function unregisterAll(string $scheme): void
	{
		unset(static::$filesystems[$scheme]);
		unset(static::$configs[$scheme]);

		stream_wrapper_unregister($scheme);
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
	public function dir_opendir(string $path, int $options): bool {
		return parent::dir_opendir($this->getPath($path), $options);
	}


	/**
	 * {@inheritDoc}
	 * @param array<string, mixed[]> $config
	 * @return array<string, mixed[]>
	 */
	protected function getConfig(string $path, array $config = []): array
	{
		$scheme = parse_url($path, PHP_URL_SCHEME);
		$name   = parse_url($path, PHP_URL_HOST);

		return array_replace_recursive(
			is_resource($this->context) ? stream_context_get_options($this->context)[$scheme] : array(),
			static::$configs[$scheme][$name],
			$config
		);
	}


	/**
	 * {@inheritDoc}
	 */
	protected function getFilesystem(string $path): FilesystemOperator
	{
		$scheme = parse_url($path, PHP_URL_SCHEME);
		$name   = parse_url($path, PHP_URL_HOST);

		return static::$filesystems[$scheme][$name];
	}


	/**
	 * Determine the target from the URI's path component <scheme>://<volume>/<target>
	 */
	protected function getPath(string $path): string
	{
		return ltrim(parse_url($path, PHP_URL_PATH) ?: '', '/');
	}
}
