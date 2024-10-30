<?php
	
	namespace Services\Signalize;
	
	class BindParser extends Parser {
		
		/**
		 * BindParser constructor
		 * @param Lexer $lexer
		 */
		public function __construct(Lexer $lexer) {
			parent::__construct($lexer);
		}
		
		/**
		 * Infer the type from the AST
		 * @param array $ast
		 * @return mixed|string
		 */
		protected function inferType(array $ast) {
			if ($ast["type"] == "bindVariableLookup") {
				return "string";
			} elseif ($ast["type"] == "stVariableLookup") {
				return "string";
			} else {
				return parent::inferType($ast);
			}
		}
		
		/**
		 * Parses a constant or variable and returns the value info
		 * @return array
		 * @throws \Exception
		 */
		protected function parseConstantOrVariable(): array {
			$token = $this->lexer->peek();
			
			switch ($token['type']) {
				case 'at' :
					$this->lexer->match('at');
					$key = $this->lexer->match('identifier');
					
					// a dot means we want to read a dynamic value on the configbuilder page
					// the part before the dot is the container, and after the dot the key
					if ($this->lexer->optionalMatch('dot')) {
						$container = $key;
						$key = $this->lexer->match('identifier');
						
						while ($this->lexer->optionalMatch('dot')) {
							$subKey = $this->lexer->match('identifier');
							$key["value"] = $key["value"] . "." . $subKey["value"];
						}
						
						return [
							'type'      => 'bindVariableLookup',
							'subType'   => "string",
							'container' => $container["value"],
							'key'       => $key["value"],
						];
					} else {
						return [
							'type'    => 'stVariableLookup',
							'subType' => "string",
							'key'     => $key["value"],
						];
					}
				
				default :
					return parent::parseConstantOrVariable();
			}
		}
		
		/**
		 * Parse the visible binding
         * 'bind' => 'enable: { @ADDON_DIFFUSE != "false" }',
		 * @return void
		 * @throws \Exception
		 */
		private function parseVisible(array &$items): void {
			$this->lexer->match('identifier');
			$this->lexer->match('colon');
			$this->lexer->match('curly_brace_open');
			$ast = $this->parseLogicalExpression();
			$this->lexer->match('curly_brace_close');
			
			// perform typechecking
			$this->typeChecker($ast);
			
			// if all well, return the item
            $items[] = [
				'type'   => 'visible',
				'ast'    => $ast,
			];
		}
		
		/**
		 * Parse the visible binding
         * 'bind' => 'visible: { @ADDON_DIFFUSE != "false" }',
         * @return void
		 * @throws \Exception
		 */
		private function parseEnabled(array &$items): void {
			$this->lexer->match('identifier');
			$this->lexer->match('colon');
			$this->lexer->match('curly_brace_open');
			$ast = $this->parseLogicalExpression();
			$this->lexer->match('curly_brace_close');
			
			// perform typechecking
			$this->typeChecker($ast);
			
			// if all well, return the item
			$items[] = [
				'type'   => 'enabled',
				'ast'    => $ast,
			];
		}
        
        /**
         * Css bind
         * 'bind' => 'css: { "text-uppercase": @configuration.LEFT_COLUMN_TITLES_CAPITALLETTERS == "true" }',
         * @return void
         * @throws \Exception
         */
        private function parseCss(array &$items): void {
            $this->lexer->match('identifier');
            $this->lexer->match('colon');
            $this->lexer->match('curly_brace_open');
            
            do {
                if (!($classNameToken = $this->lexer->optionalMatch("string"))) {
                    $classNameToken = $this->lexer->match("identifier");
                }
                
                $this->lexer->match('colon');
                
                $items[] = [
                    'type'  => 'css',
                    'class' => $classNameToken["value"],
                    'ast'   => $this->parseLogicalExpression(),
                ];
            } while ($this->lexer->optionalMatch('comma'));
            
            $this->lexer->match('curly_brace_close');
        }
		
        /**
         * Css bind
         * 'bind' => 'style: { "--btn-primary-bg-color": @configuration.THEME_BTN_COLOR_PRIMARY, "--btn-primary-text-color": @configuration.THEME_BTN_TEXT_COLOR_PRIMARY, "--btn-primary-border-color": @configuration.THEME_BTN_BORDER_COLOR_PRIMARY, "--btn-primary-bg-color-hover": @configuration.THEME_BTN_HOVER_COLOR_PRIMARY, "--btn-primary-text-color-hover": @configuration.THEME_BTN_TEXT_HOVER_COLOR_PRIMARY, "--btn-primary-border-color-hover": @configuration.THEME_BTN_BORDER_HOVER_COLOR_PRIMARY }',
		 * @return void
         * @throws \Exception
         */
        private function parseStyle(array &$items): void {
            $this->lexer->match('identifier');
            $this->lexer->match('colon');
            $this->lexer->match('curly_brace_open');
            
            do {
                if (!($classNameToken = $this->lexer->optionalMatch("string"))) {
                    $classNameToken = $this->lexer->match("identifier");
                }
                
                $this->lexer->match('colon');
                
                $items[] = [
                    'type'  => 'style',
                    'class' => $classNameToken["value"],
                    'ast'   => $this->parseExpression(),
                ];
            } while ($this->lexer->optionalMatch('comma'));
            
            $this->lexer->match('curly_brace_close');
        }
		
		/**
		 * Parse the visible binding
		 * 'bind' => 'options: { "abc": <expression> }',
		 * @return void
		 * @throws \Exception
		 */
		private function parseOptions(array &$items): void {
			$subItems = [];
			
			$this->lexer->match('identifier');
			$this->lexer->match('colon');
			$this->lexer->match('curly_brace_open');
			
			do {
				if (!($name = $this->lexer->optionalMatch("string"))) {
					$name = $this->lexer->match("identifier");
				}
				
				$this->lexer->match('colon');
				
				$subItems[] = [
                    'name' => $name["value"],
                    'ast'  => $this->parseLogicalExpression()
                ];
			} while ($this->lexer->optionalMatch('comma'));
			
			$this->lexer->match('curly_brace_close');
			
			$items[] = [
				'type'  => 'options',
				'items' => $subItems
			];
		}
		
		/**
		 * Parsers the input string
		 * @param array $globalVariables
		 * @return array
		 * @throws \Exception
		 */
		public function parse(array $globalVariables=[]): array {
			$result = [];
			$token = $this->lexer->peek();
			
			do {
				switch ($token['value']) {
					case 'visible' :
						$this->parseVisible($result);
						break;
					
					case 'enabled' :
						$this->parseEnabled($result);
						break;
					
					case 'css' :
						$this->parseCss($result);
						break;
					
					case 'style' :
						$this->parseStyle($result);
						break;
					
					case 'options' :
						$this->parseOptions($result);
						break;
					
					default :
						throw new \Exception("SyntaxError: Illegal binding {$token['value']}");
				}
			} while ($this->lexer->optionalMatch('comma'));
			
			return $result;
		}
	}