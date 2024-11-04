<?php
	
	namespace Services\Http\Response;
	
	use Smarty;
	use Symfony\Component\HttpFoundation\Response;
	
	class SmartyResponse extends Response {

		private Smarty $smarty;
		private string $template;
		private array $variables = [];
		
		/**
		 * Smarty configuratie
		 * @var array
		 */
		private static array $smartyConfig = [
			'template_dir' => 'templates/',
			'compile_dir'  => 'cache/templates_c/',
			'cache_dir'    => 'cache/',
			'config_dir'   => 'config/'
		];
		
		private function __construct(string $template, array $variables = [], int $status = 200, array $headers = []) {
			parent::__construct('', $status, $headers);
			
			$this->template = $template;
			$this->variables = $variables;
			
			$this->initializeSmarty();
		}
		
		/**
		 * Create a new SmartyResponse instance
		 */
		public static function create(string $template, array $variables = [], int $status = 200, array $headers = []): self {
			return new self($template, $variables, $status, $headers);
		}
		
		/**
		 * Configure Smarty settings globally
		 * @param array $config
		 * @return void
		 */
		public static function configureSmarty(array $config): void {
			self::$smartyConfig = array_merge(self::$smartyConfig, $config);
		}
		
		/**
		 * Initialize Smarty with configured settings
		 * @return void
		 */
		private function initializeSmarty(): void {
			$this->smarty = new Smarty();
			
			// Apply configuration
			foreach (self::$smartyConfig as $key => $value) {
				if (property_exists($this->smarty, $key)) {
					$this->smarty->$key = $value;
				}
			}
			
			// Additional Smarty configuration
			$this->smarty->debugging = false;
			$this->smarty->caching = Smarty::CACHING_OFF;
		}
		
		/**
		 * Prepare the response before sending
		 * @return SmartyResponse
		 * @throws \SmartyException
		 */
		public function prepareSmartyResponse(): self {
			// Assign all variables to Smarty
			foreach ($this->variables as $key => $value) {
				$this->smarty->assign($key, $value);
			}
			
			// Render the template
			$content = $this->smarty->fetch($this->template);
			$this->setContent($content);
			
			// Set content type if not already set
			if (!$this->headers->has('Content-Type')) {
				$this->headers->set('Content-Type', 'text/html; charset=UTF-8');
			}
			
			return $this;
		}
		
		/**
		 * Send the response
		 * @param bool $flush
		 */
		public function send(bool $flush = true): static {
			$this->prepareSmartyResponse();
			return parent::send();
		}
	}