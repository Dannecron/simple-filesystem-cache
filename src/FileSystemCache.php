<?php

namespace Sarahman\SimpleCache;

use Psr\SimpleCache\CacheInterface;

/**
 * File System Cache using PSR simple cache interface implementation.
 *
 * @package    FileSystemCache
 * @author     Syed Abidur Rahman <aabid048@gmail.com>
 * @copyright  2018 Syed Abidur Rahman
 * @license    https://opensource.org/licenses/mit-license.php MIT
 * @version    1.0.0
 */
class FileSystemCache implements CacheInterface
{
    /**
     * runtime cached data storage
     * @var array
     */
    private $cache = null;

    /**
     * cache path
     * @var string
     */
    private $cacheDirectory;

    /**
     * Create a cache instance
     * @param string $cacheDirectory
     * @throws \Exception
     */
    public function __construct(string $cacheDirectory = '')
    {
        if (empty($cacheDirectory)) {
            $cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, __CLASS__);
        } elseif (preg_match('#^\./#', $cacheDirectory)) {
            $cacheDirectory = preg_replace('#^\./#', '', $cacheDirectory);
            $cacheDirectory = getcwd() . DIRECTORY_SEPARATOR . ltrim($cacheDirectory, DIRECTORY_SEPARATOR);
        }

        if (!is_dir($cacheDirectory)) {
            $uMask = umask(0);
            @mkdir($cacheDirectory, 0755, true);
            umask($uMask);
        }

        if (!is_dir($cacheDirectory) || !is_readable($cacheDirectory)) {
            throw new \Exception('The root path ' . $cacheDirectory . ' is not readable.');
        }

        $this->cacheDirectory = rtrim($cacheDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null)
    {
        if (!$this->isKey($key)) {
            return false;
        }

        $timeToLive = $ttl;
        if ($ttl === null) {
            $timeToLive = 3600;
        } elseif ($ttl instanceof \DateInterval) {
            $reference = new \DateTimeImmutable();
            $endTime = $reference->add($ttl);
            $timeToLive = $endTime->getTimestamp() - $reference->getTimestamp();
        }


        if ($data = json_encode(array('lifetime' => time() + $timeToLive, 'data' => $value))) {
            if (file_put_contents($this->cacheDirectory . $key, $data) !== false) {
                $this->cache[$key] = $data;
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
    {
        if (!$this->isKey($key)) {
            return false;
        }

        $file = $this->cacheDirectory . $key;

        if (isset($this->cache[$key])) {
            $fileData = $this->cache[$key];
        } else {
            $fileData = @file_get_contents($file);
            $this->cache[$key] = $fileData;
        }

        if ($fileData !== false) {
            // check if empty (file with failed write/unlink)
            if (!empty($fileData)) {
                $fileData = json_decode($fileData, true);
                if (isset($fileData['lifetime'], $fileData['data'])) {
                    if ($fileData['lifetime'] >= time()) {
                        return $fileData['data'];
                    } else {
                        $this->deleteFile($file);
                    }
                }
            } else {
                $this->deleteFile($file);
            }
        }

        return $default;
    }

    /**
     * @inheritDoc
     */
    public function delete($key)
    {
        if (!$this->isKey($key))
            return false;

        return $this->deleteFile($this->cacheDirectory . $key);
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        return $this->deleteByPattern('*');
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null)
    {
        $values = array();
        foreach ($keys AS $key) {
            $values[$key] = $this->get($key, $default);
        }
        return $values;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null)
    {
        $return = false;
        foreach ($values AS $key => $value) {
            $return = $this->set($key, $value, $ttl) || $return;
        }
        return $return;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys)
    {
        $return = false;
        foreach ($keys AS $key) {
            $values[$key] = $this->delete($key);
        }
        return $return;
    }

    /**
     * @inheritDoc
     */
    public function has($key)
    {
        $value = $this->get($key);
        return !empty($value);
    }

    /**
     * Fetched all the cached data.
     *
     * @return array|null
     */
    public function all(): ?array
    {
        return $this->cache;
    }

    /**
     * set a new expiration on an item
     * @param string $key The key under which to store the value.
     * @param integer $lifetime The expiration time, defaults to 3600
     * @return boolean
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function touch(string $key, int $lifetime = 3600): bool
    {
        if ($data = $this->get($key)) {
            return $this->set($key, $data, $lifetime);
        }

        return false;
    }

    /**
     * Delete item matching pattern sintax
     * @param string $pattern The pattern (@see glob())
     * @return bool Returns TRUE on success or FALSE on failure
     */
    public function deleteByPattern(string $pattern = '*'): bool
    {
        $return = true;
        $glob = glob($this->cacheDirectory . $pattern, GLOB_NOSORT | (defined('GLOB_BRACE') ? GLOB_BRACE : 0));
        foreach ($glob as $cacheFile) {
            if (!$this->deleteFile($cacheFile)) {
                $return = false;
            }
        }

        return $return;
    }

    /**
     * check if $key is valid key name
     * @param string $key The key to validate
     * @return boolean Returns TRUE if valid key or FALSE otherwise
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function isKey(string $key): bool
    {
        try {
            return !preg_match('/[^a-z_\-0-9.]/i', $key);
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * delete a file
     * @param string $cacheFile
     * @return bool
     */
    private function deleteFile(string $cacheFile): bool
    {
        unset($this->cache[basename($cacheFile)]);

        clearstatcache(true, $cacheFile);

        if (file_exists($cacheFile)) {
            if (is_file($cacheFile) && !@unlink($cacheFile)) {
                return (file_put_contents($cacheFile, '') !== false);
            }

            clearstatcache(true, $cacheFile);
        }

        return true;
    }
}
