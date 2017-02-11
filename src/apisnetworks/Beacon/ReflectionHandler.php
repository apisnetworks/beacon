<?php

	namespace apisnetworks\Beacon;
	use GuzzleHttp\Exception\GuzzleException;
	use TokenReflection\Broker;

	class ReflectionHandler {
		/**
		 * @const integer maximum time in seconds cache is considered fresh
		 */
		const MIN_CACHE_TIME = 86400;
		/**
		 * @var string apnscp module name
		 */
		protected $moduleName;
		/**
		 * @var string local file source
		 */
		protected $fileSource;
		/**
		 * @var static reflection instance
		 */
		protected $reflection;

		public function __construct($file) {
			$this->moduleName = $this->makeModuleFromFile($file);
			if ($this->isUrl($file)) {
				if ($this->isCacheStale($file)) {
					$this->download($file);
				}
				$file = Helpers::getCachePath($file);
			}
			$this->fileSource = $file;
			$this->analyze($this->fileSource);
		}

		protected function isUrl($file) {
			return !strncmp($file, 'http://', 7) ||
				!strncmp($file, 'https://', 8);
		}

		/**
		 * Tokenize source
		 *
		 * @param $file
		 * @return bool|\TokenReflection\ReflectionFile
		 */
		protected function analyze($file) {
			$this->reflection = new Broker(new Broker\Backend\Memory());
			return $this->reflection->processFile($file);
		}

		protected function download($url) {
			$client = new \GuzzleHttp\Client();
			$tmp = Helpers::getCachePath($url);
			$parent = dirname($tmp);
			if (!is_dir($parent)) {
				mkdir($parent);
			}
			try {
				$client->request('GET', $url, ['sink' => $tmp]);
			} catch (GuzzleException $e) {
				unlink($tmp);
				throw $e;
			}

			return true;
		}

		protected function isCacheStale($file) {
			$path = Helpers::getCachePath($file);
			if (!file_exists($path)) {
				return true;
			}

			return ( (time() - filemtime($path)) > self::MIN_CACHE_TIME );
		}
		
		protected function makeModuleFromFile($file) {
			$file = basename($file, '.php');
			return ucwords($file) . '_Module';
		}

		public function allMethods() {
			return $this->reflection->getMethods();
		}

		public function getCodeFromMethod($method) {
			$class = $this->reflection->getClass($this->moduleName);
			$method = $class->getMethod($method);
			print rtrim($method->getSource()). PHP_EOL;
		}
}