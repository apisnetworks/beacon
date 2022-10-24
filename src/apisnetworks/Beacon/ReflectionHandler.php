<?php

	namespace apisnetworks\Beacon;
	use GuzzleHttp\Exception\GuzzleException;
	use Roave\BetterReflection\BetterReflection;
	use Roave\BetterReflection\Reflector\ClassReflector;
	use Roave\BetterReflection\SourceLocator\Type\StringSourceLocator;

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
		 * @var ClassReflector reflection instance
		 */
		protected $reflection;
		/**
		 * @var string raw code
		 */
		protected $code;

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
		 * @return ReflectionFile
		 */
		protected function analyze($file) {
			$this->code = preg_replace('/\sextends\s+.+$/m', '', file_get_contents($file), 1);
			$this->reflection = new ClassReflector(new StringSourceLocator($this->code, (new BetterReflection())->astLocator()));
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

			$class = $this->reflection->reflect($this->moduleName);
			$method = $class->getMethod($method);
			print $method->getDocComment() . PHP_EOL;
			$source = implode('', array_slice(file($this->fileSource), $method->getStartLine()-1, $method->getEndLine()-$method->getStartLine()+1));
			if (preg_match('/^(\s+)/', $source, $ws)) {
				$source = preg_replace('/^' . $ws[1] . '/m', '', $source);
			}
			print $source;
		}
}
