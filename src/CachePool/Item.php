<?php
	
	namespace Quellabs\ObjectQuel\CachePool;
	
	class Item {
		const cacheNone = 0x00;
		const cacheHit = 0x01;
		
		private $key;
		private $data;
		private $state;
		private $oldExpire;
		private $expire;
		private $delta;
		
		/**
		 * Item constructor
		 * @param string $key
		 * @param string|array|null $data
		 * @param int $state
		 * @param \DateTime|null $oldExpire
		 * @param \DateTime|null $expire
		 * @param int $delta
		 */
		public function __construct(string $key, $data = null, int $state = self::cacheNone, ?\DateTime $oldExpire = null, ?\DateTime $expire = null, int $delta = 0) {
			$this->key = $key;
			$this->data = $data;
			$this->state = $state;
			$this->oldExpire = $oldExpire;
			$this->expire = $expire;
			$this->delta = $delta;
		}
		
		/**
		 * Returns the cached data
		 * @return string
		 */
		public function getKey(): string {
			return $this->key;
		}
		
		/**
		 * Returns the cached data
		 * @return mixed
		 */
		public function get() {
			return $this->data;
		}
		
		/**
		 * Sets/updates cached data
		 * @return mixed
		 */
		public function set($data) {
			$this->data = $data;
		}
		
		/**
		 * Returns the old expire date (e.g. the one obtained from the database)
		 * @return \DateTime|null
		 */
		public function getOldExpire(): ?\DateTime {
			return $this->oldExpire;
		}
		
		/**
		 * Returns the new expire date (e.g. the one set in the callable)
		 * @return \DateTime|null
		 */
		public function getExpire(): ?\DateTime {
			return $this->expire;
		}
		
		/**
		 * Sets the expire date
		 * @param mixed $expire
		 */
		public function expiresAfter($expire) {
			if ($expire instanceof \DateInterval) {
				$this->expire = new \DateTime();
				$this->expire->add($expire);
			} elseif ($expire instanceof \DateTime) {
				$this->expire = $expire;
			} elseif (is_numeric($expire)) {
				if ($expire < 0) {
					$this->expire = \DateTime::createFromFormat('Y-m-d', '1990-01-01');
				} else {
					$expireInMs = round($expire * 1000);
					$this->expire = new \DateTime();
					$this->expire->modify("+{$expireInMs} ms");
				}
			} elseif (is_string($expire)) {
				$this->expire = new \DateTime();
				$this->expire->modify($expire);
			} elseif (is_null($expire)) {
				$this->expire = null;
			}
		}
		
		/**
		 * Returns true if the cache was hit, false if not
		 * @return bool
		 */
		public function isHit(): bool {
			if ($this->oldExpire !== null) {
				$dateNow = new \DateTime();
				$expired = $dateNow > $this->oldExpire;
			} else {
				$expired = false;
			}
			
			return ($this->state & self::cacheHit) && !$expired;
		}
		
		/**
		 * Returns the delta (the time it takes to regenerate the cache)
		 * @return int
		 */
		public function getDelta(): int {
			return $this->delta;
		}
		
		/**
		 * Sets the delta (the time it takes to regenerate the cache)
		 * @param int $delta
		 * @return void
		 */
		public function setDelta(int $delta) {
			$this->delta = $delta;
		}
	}