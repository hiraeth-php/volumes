<?php

namespace Hiraeth\Volumes;

use Traversable;
use Iterator;
use RuntimeException;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToReadFile;

/**
 *
 */
class StreamWrapper
{
	/**
	 * For internal calls
	 *
	 * @var string
	 */
	const INTERNAL = '_@@@@@@@INTERNAL@@@@@@@_';

	/**
	 * @var array<string, array<string, FileSystemOperator>>
	 */
	static protected $filesystems = [];

	/**
	 * @var resource
	 */
	public $context;

	/**
	 * @var Traversable<StorageAttributes>|null
	 */
	protected $directory;

	/**
	 * @var FileSystemOperator|null
	 */
	protected $filesystem;

	/**
	 * @var string|null
	 */
	 protected $location;

	 /**
	 * @var resource|null
	 */
	protected $handle = NULL;

	/**
	 * @var bool
	 */
	protected $readOnly = FALSE;

	/**
	 *  @var bool
	 */
	protected $writeOnly = FALSE;


	/**
	 * Get a filesystem for a URI
	 */
	static public function getFilesystem(string $uri): FileSystemOperator
	{
		$scheme = parse_url($uri, PHP_URL_SCHEME);
		$name   = parse_url($uri, PHP_URL_HOST);

		if (!isset(static::$filesystems[$scheme][$name])) {
			throw new RuntimeException(sprintf(
				'Cannot get filesystem for scheme "%s", "%s" is not registered',
				$scheme,
				$name
			));
		}

		return static::$filesystems[$scheme][$name];
	}


	/**
	 * Get a path for a URI
	 */
	static public function getPath(string $uri): string
	{
		return ltrim(parse_url($uri, PHP_URL_PATH) ?: '', '/');
	}


	/**
	 * Register a filesystem
	 */
	static public function register(string $scheme, string $name, FilesystemOperator $filesystem): void
	{
		if (!isset(static::$filesystems[$scheme])) {
			if (in_array($scheme, stream_get_wrappers())) {
				throw new RuntimeException(sprintf(
					'Cannot register scheme "%s", already registered',
					$scheme
				));
			}

			static::$filesystems[$scheme] = [];

			stream_wrapper_register($scheme, static::class);
		}

		if (isset(static::$filesystems[$scheme][$name])) {
			throw new RuntimeException(sprintf(
				'Cannot register filesystem on scheme "%s", "%s" is already registered',
				$scheme,
				$name
			));
		}

		static::$filesystems[$scheme][$name] = $filesystem;
	}


	/**
	 * Unregister a filesystem
	 */
	static public function unregister(string $scheme, string $name): void
	{
		if (!isset(static::$filesystems[$scheme])) {
			throw new RuntimeException(sprintf(
				'Cannot unregister on scheme "%s", not registered',
				$scheme
			));
		}

		if (!isset(static::$filesystems[$scheme][$name])) {
			throw new RuntimeException(sprintf(
				'Cannot unregister on scheme "%s", "%s" is not not registered',
				$scheme,
				$name
			));
		}

		unset(static::$filesystems[$scheme][$name]);
	}


	/**
	 * {@inheritDoc}
	 */
	public function dir_closedir(): bool
	{
		$this->location  = NULL;
		$this->directory = NULL;

		return TRUE;
	}


	/**
	 * {@inheritDoc}
	 */
	public function dir_opendir(string $uri, int $options): bool
	{
		$this->location   = static::getPath($uri);
		$this->filesystem = static::getFilesystem($uri);

		$this->dir_rewinddir();

		return TRUE;
	}


	/**
	 * {@inheritDoc}
	 */
	public function dir_readdir(): string|false
	{
		if (!$this->directory instanceof Iterator) {
			return FALSE;
		}

		if (!$this->directory->valid()) {
			return FALSE;
		}

		if (!$current = $this->directory->current()) {
			return FALSE;
		}

		$this->directory->next();

		return $current->path();
	}


	/**
	 * {@inheritDoc}
	 */
	public function dir_rewinddir(): bool
	{
		if (!$this->directory) {
			$this->directory = $this->filesystem
				->listContents($this->location, FALSE)
				->getIterator()
			;

		} else {
			$this->directory = NULL;

			$this->dir_rewinddir();
		}

		return TRUE;
	}


	/**
	 * {@inheritDoc}
	 */
	public function mkdir(string $uri, int $mode, int $options): bool
	{
		static::getFilesystem($uri)->createDirectory(static::getPath($uri));

		return TRUE;
	}


	/**
	 * {@inheritDoc}
	 */
	public function rename(string $src_uri, string $dst_uri): bool
	{
		$src_filesystem = static::getFilesystem($src_uri);
		$dst_filesystem = static::getFilesystem($dst_uri);

		if ($src_filesystem === $dst_filesystem) {
			$src_filesystem->move(static::getPath($src_uri), static::getPath($dst_uri));

		} else {

			unlink($src_uri);
		}

		return TRUE;
	}


	/**
	 * {@inheritDoc}
	 */
	public function rmdir(string $uri, int $options): bool
	{
		static::getFilesystem($uri)->deleteDirectory(static::getPath($uri));

		return TRUE;
	}


	/**
	 * {@inheritDoc}
	 */
	public function unlink(string $uri): bool
	{
		static::getFilesystem($uri)->delete(static::getPath($uri));

		return TRUE;
	}


	/**
	 * {@inheritDoc}
	 *
	 * @return resource
	 */
	public function stream_cast(int $cast_as)
	{
		return $this->handle;
	}


	/**
	 * {@inheritDoc}
	 */
	public function stream_close(): void
	{
		$this->location = NULL;

		if (is_resource($this->handle)) {
			fclose($this->handle);

			$this->handle = NULL;
		}
	}


	/**
	 * {@inheritDoc}
	 */
	public function stream_eof(): bool
	{
		return feof($this->handle);
	}


	/**
	 * {@inheritDoc}
	 */
	public function stream_flush(): bool
	{
		if ($this->readOnly) {
			return FALSE;
		}

		try {
			$this->filesystem->writeStream($this->location, $this->handle);
		} catch (UnableToWriteFile) {
			return FALSE;
		}

		return TRUE;
	}


	/**
	 * {@inheritDoc}
	 */
	public function stream_lock(int $operation): bool
	{
		return TRUE;
	}


	/**
	 * {@inheritDoc}
	 */
	public function stream_metadata(string $uri, int $option, mixed $value): bool
	{
		$filesystem = static::getFilesystem($uri);
		$location   = static::getPath($uri);

		if ($option == STREAM_META_TOUCH) {
			$mtime = $value[0] ?? time();
			$atime = $value[1] ?? time();

			if (!$filesystem->fileExists($location)) {
				$filesystem->write($location, '');
			}

			//
			// TODO: figure out how to actually update times
			//
		}

		if ($option == STREAM_META_GROUP_NAME) {
			$filesystem->setVisibility($location, $value);
		}

		return TRUE;
	}


	/**
	 * {@inheritDoc}
	 */
	public function stream_open(string $uri, string $mode, int $options, ?string &$opened_path = null): bool
	{
		$this->filesystem = static::getFilesystem($uri);
		$this->location   = static::getPath($uri);

		if ($mode[0] == 'r') {
			if (!$this->filesystem->fileExists($this->location)) {
				return FALSE;
			}
		}

		if ($mode[0] == 'x') {
			if ($this->filesystem->fileExists($this->location)) {
				return FALSE;
			}
		}

		if (in_array($mode[0], ['a', 'c', 'w', 'x'])) {
			if (!$this->filesystem->fileExists($this->location)) {
				$this->filesystem->write($this->location, '');
			}
		}

		if (in_array($mode, ['r', 'rb'])) {
			$this->readOnly = TRUE;
		}

		if (in_array($mode[0], ['a', 'c', 'w', 'x']) && !strpos($mode, '+')) {
			$this->writeOnly = TRUE;
		}

		try {
			if ($this->readOnly) {
				$this->handle = $this->filesystem->readStream($this->location);

			} else {
				$memory_limit = ini_get('memory_limit');
				$memory_used  = memory_get_peak_usage();

				if (is_numeric($memory_limit)) {
					$memory_left = 512 * 1024 * 1024;

				} else {
					$memory_left  = (
						(int) $memory_limit
						* 1024
						* ['k' => 1, 'm' => 2, 'g' => 3][substr(strtolower($memory_limit), -1)]
						- $memory_used
					);
				}

				if ($this->filesystem->fileSize($this->location) <= $memory_left * .5) {
					$this->handle = fopen('php://memory', 'r+') ?: NULL;
				} else {
					$this->handle = fopen('php://temp', 'r+') ?: NULL;
				}

				if (!$this->handle) {
					return FALSE;
				}

				if ($mode[0] != 'w') {
					$source = $this->filesystem->readStream($this->location);

					stream_copy_to_stream($source, $this->handle);

					fclose($source);

				} else {
					$this->stream_flush();
				}
			}
		} catch (UnableToReadFile) {
			return FALSE;
		}

		return TRUE;
	}


	/**
	 * {@inheritDoc}
	 *
	 * @param int<0, max> $count
	 */
	public function stream_read(int $count): string|false
	{
		if ($this->writeOnly) {
			return FALSE;
		}

		return fread($this->handle, $count);
	}


	/**
	 * {@inheritDoc}
	 */
	public function stream_seek(int $offset, int $whence = SEEK_SET): bool
	{
		return fseek($this->handle, $offset, $whence) === 0;
	}


	/**
	 * {@inheritDoc}
	 */
	public function stream_set_option(int $option, int $arg1, ?int $arg2 = null): bool
	{
		if ($option === STREAM_OPTION_BLOCKING) {
			return stream_set_blocking($this->handle, $arg1 ? TRUE : FALSE);
		}

		if ($option === STREAM_OPTION_READ_TIMEOUT) {
			return stream_set_timeout($this->handle, $arg1, $arg2);
		}

		return stream_set_write_buffer($this->handle, $arg2) === 0;
	}


	/**
	 * {@inheritDoc}
	 *
	 * @return array<int|string, mixed>|false
	 */
	public function stream_stat(): array|false
	{
		$stats = fstat($this->handle);

		if (!$stats) {
			return FALSE;
		}

		if ($stats[11] <= 0) {
			$stats[11] = $stats['blksize'] = 512;
		}

		$stats[12] = $stats['blocks'] = (int) ceil($stats['size'] / 512);

		if ($this->readOnly) {
			return $stats;
		}

		$url_stats = $this->url_stat(static::INTERNAL, STREAM_URL_STAT_QUIET);

		if (!$url_stats) {
			return FALSE;
		}

		foreach ([
			0  => 'dev',
			1  => 'ino',
			2  => 'mode',
			3  => 'nlink',
			4  => 'uid',
			5  => 'gid',
			6  => 'rdev',
			8  => 'atime',
			9  => 'mtime',
			10 => 'ctime',
			11 => 'blksize',
			12 => 'blocks',
		] as $key => $name) {
			$stats[$key] = $stats[$name] = $url_stats[$name];
		}

		return $stats;
	}

	/**
	 * Determine the current cursor position in the stream
	 */
	public function stream_tell(): int
	{
		return (int) ftell($this->handle);
	}


	/**
	 * Truncate the stream to a given size
	 *
	 * @param int<0, max> $size
	 */
	public function stream_truncate(int $size): bool
	{
		return ftruncate($this->handle, $size);
	}


	/**
	 * {@inheritDoc}
	 */
	public function stream_write(string $data): int|false
	{
		if ($this->readOnly) {
			return FALSE;
		}

		return fwrite($this->handle, $data);
	}


	/**
	 * {@inheritDoc}
	 *
	 * @return array<int|string, mixed>|false
	 */
	public function url_stat(string $uri, int $flags): array|false
	{
		if ($uri == static::INTERNAL) {
			$filesystem = $this->filesystem;
			$location   = $this->location;
		} else {
			$filesystem = static::getFilesystem($uri);
			$location   = static::getPath($uri);
		}

		try {
			$stats = fstat($filesystem->readStream($location));
		} catch (UnableToReadFile) {
			return FALSE;
		}

		return $stats;
	}
}
