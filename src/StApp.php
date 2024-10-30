<?php
	
	use Services\EntityManager\databaseAdapter;
	use Services\EntityManager\EntityManager;
	
	//error_reporting(E_ALL);
	//ini_set('display_errors', true);
	
    // composer
    require_once(dirname(__FILE__) . '/vendor/autoload.php');
    
    // base
    require_once(dirname(__FILE__) . '/controllers/controllerBase.php');
    require_once(dirname(__FILE__) . '/blocks/ApiBase.php');
    require_once(dirname(__FILE__) . '/components/comBase.php');
	require_once(dirname(__FILE__) . '/delegates/delegateBase.php');
	require_once(dirname(__FILE__) . '/custom/customBase.php');

    // cron expression parser
    require_once(dirname(__FILE__) . '/vendor_st/cron/CronExpression.php');
    require_once(dirname(__FILE__) . '/vendor_st/cron/FieldFactory.php');
    require_once(dirname(__FILE__) . '/vendor_st/cron/FieldInterface.php');
    require_once(dirname(__FILE__) . '/vendor_st/cron/AbstractField.php');
    require_once(dirname(__FILE__) . '/vendor_st/cron/MinutesField.php');
    require_once(dirname(__FILE__) . '/vendor_st/cron/HoursField.php');
    require_once(dirname(__FILE__) . '/vendor_st/cron/DayOfMonthField.php');
    require_once(dirname(__FILE__) . '/vendor_st/cron/DayOfWeekField.php');
    require_once(dirname(__FILE__) . '/vendor_st/cron/MonthField.php');
    
    // ================================================
    // include configuration keys and database class
    // ================================================

    $current_dir = getcwd();
    $api_dir = dirname(__FILE__);
    chdir($api_dir);
    chdir("..");
	$parentDir = getcwd();

    require_once($parentDir . '/includes/configure.php');
    require_once($parentDir . '/includes/classes/Database/clsDB.php');
    require_once($parentDir . '/includes/classes/clsTools.php');
    require_once($parentDir . '/includes/classes/navigation_history.php');
    require_once($parentDir . '/includes/classes/recent_history.php');
	require_once($parentDir . '/includes/libs/plugins/function.picture.php');

    chdir($current_dir);

    /**
     * Class BasicEnum
     * @url https://stackoverflow.com/questions/254514/php-and-enumerations
     */
    abstract class BasicEnum {
        private static $constCache = null;
    
        /**
         * Returns all the constants in this Enum
         * @return array|null
         * @throws ReflectionException
         */
        public static function getConstants(): ?array {
            if (self::$constCache === null) {
                $reflect = new ReflectionClass(get_called_class());
                self::$constCache = $reflect->getConstants();
            }

            return self::$constCache;
        }
    
        /**
         * Returns the lowest value in this enum
         * @return mixed
         * @throws ReflectionException
         */
        public static function lowestValue() {
            $values = array_values(self::getConstants());
            return min($values);
        }
    
        /**
         * Returns true if the given name is present in the enum, false if not
         * @param string $name
         * @param bool $strict
         * @return bool
         * @throws ReflectionException
         */
        public static function isValidName(string $name, $strict = false): bool {
            $constants = self::getConstants();

            if ($strict) {
                return array_key_exists($name, $constants);
            }

            $keys = array_map('strtolower', array_keys($constants));
            return in_array(strtolower($name), $keys);
        }
    
        /**
         * Returns true if the given value is present in the enum, false if not
         * @param $value
         * @return bool
         * @throws ReflectionException
         */
        public static function isValidValue($value): bool {
            $values = array_values(self::getConstants());
            return in_array($value, $values);
        }
    
        /**
         * Converts a key to a value
         * @param string $key
         * @param bool $strict
         * @return bool|int|string
         */
        public static function toValue(string $key, $strict = false) {
            $constants = self::getConstants();
            
            if ($strict) {
                return array_key_exists($key, $constants) ? $constants[$key] : false;
            }
            
            foreach($constants as $k => $v) {
                if (strcasecmp($key, $k) == 0) {
                    return $v;
                }
            }
            
            return false;
        }
    
        /**
         * Converts a value to a key
         * @param mixed $value
         * @return bool|int|string
         * @throws ReflectionException
         */
        public static function toString($value) {
            return array_search($value, self::getConstants());
        }
    }
    
    class EnumReasons extends BasicEnum {
        const Unknown           = -1;
        const OrderPlaced       = 1;
        const OrderRemoved      = 2;
        const StockChange       = 3;
        const BackorderReceived = 4;
        const ManualChange 		= 5;
        const OrderChanged 		= 6;
    }

    class EnumStockTableFilter extends BasicEnum {
        const All = -1;
        const OnlyActive = 1;
        const OnlyInActive = 2;
    }

    class EnumResult extends BasicEnum {
        const Error = 0;
        const Success = 1;
        const NoAction = 2;
        const CategoryNotPresent = 3;
        const ProductNotPresent = 4;
        const CannotMoveToOwnChild = 5;
    }

    class EnumQuantityType extends BasicEnum {
        const Web = 1;
        const Physical = 2;
        const Minimum = 3;
        const External = 4;
        const Maximum = 5;
    }
    
    class SessionManager implements SessionHandlerInterface {
        private databaseAdapter $db;

        /**
         * SessionManager constructor.
         * @param $clsDB
         */
        public function __construct(databaseAdapter $clsDB) {
            $this->db = $clsDB;
        }
		
		/**
		 * Database: session close
		 * @return bool
		 */
		public function close(): bool {
			return true;
		}
		
		/**
		 * Database: remove a session
		 * @return bool
		 */
		public function destroy(string $id): bool {
			$idRes = $this->db->qstr($id);
			
			$this->db->Execute("
				DELETE
				FROM `sessions`
				WHERE `sesskey`='{$idRes}'
			");
			
			return true;
		}
		
		/**
		 * Database: garbage collect old sessions
		 * @param int $max_lifetime
		 * @return int|false
		 */
		public function gc(int $max_lifetime): int|false {
			$lifeTime = $this->db->qstr(time());
			
			$this->db->Execute("
				DELETE
				FROM `sessions`
				WHERE `expiry` < {$lifeTime}
			");
			
			return true;
		}
		
		/**
         * Database: session open
         * @return bool
         */
        public function open(string $path, string $name): bool {
            return true;
        }

		/**
		 * Database: read a session from the db
		 * @param string $id
		 * @return string|false
		 */
        public function read(string $id): string|false {
            $idRes = $this->db->qstr($id);
            
			$value = $this->db->GetOne("
				SELECT
					`value`
				FROM `sessions`
				WHERE `sesskey`='{$idRes}'
			");

            if ($value === false) {
                return '';
            }

            return (string)$value;
        }

        /**
         * Database: write a session to the db
         * @return bool
         */
        public function write(string $id, string $data): bool {
            $idRes = $this->db->qstr($id);
            $expiryTime = time() + ini_get('session.gc_maxlifetime');
            $valueRes = $this->db->qstr($data);
            
            $this->db->Execute("
                INSERT INTO `sessions` (`sesskey`, `expiry`, `value`)
                VALUES ('{$idRes}', {$expiryTime}, '{$valueRes}')
                ON DUPLICATE KEY UPDATE
                    `expiry` = VALUES(`expiry`),
                    `value` = VALUES(`value`)
            ");
            
            return true;
        }
    }

    /**
     * @property comBasket $comBasket
     * @property comBeheermodule $comBeheermodule
     * @property comBlog $comBlog
     * @property comBox $comBox
     * @property comCache $comCache
     * @property comCategory $comCategory
     * @property comChannable $comChannable
     * @property comConfigure $comConfigure
     * @property comCore $comCore
     * @property comCountry $comCountry
     * @property comCoupon $comCoupon
     * @property comCriteria $comCriteria
     * @property comCurrency $comCurrency
     * @property comCustomer $comCustomer
     * @property comDebug $comDebug
     * @property comFeature $comFeature
	 * @property comFietsKoerier $comFietsKoerier
	 * @property comFilter $comFilter
     * @property comFixes $comFixes
     * @property comFlashBanner $comFlashBanner
     * @property comGoogleAuthentication $comGoogleAuthentication
     * @property comInfoPage $comInfoPage
     * @property comKiyoh $comKiyoh
     * @property comLanguage $comLanguage
     * @property comLayout $comLayout
     * @property comLogAction $comLogAction
     * @property comManufacturer $comManufacturer
     * @property comMenus $comMenus
     * @property comMinify $comMinify
     * @property comMontapacking $comMontapacking
     * @property comOcs $comOcs
     * @property comOption $comOption
     * @property comOrder $comOrder
     * @property comPicqer $comPicqer
     * @property comPixel $comPixel
     * @property comPlugin $comPlugin
     * @property comPostNLCheckout $comPostNLCheckout
     * @property comMyParcelCheckout $comMyParcelCheckout
     * @property comProduct $comProduct
     * @property comRecommender $comRecommender
     * @property comRecommenderItem $comRecommenderItem
     * @property comRecommenderUser $comRecommenderUser
     * @property comRecommenderStats $comRecommenderStats
     * @property comReeleezee $comReeleezee
     * @property comReports $comReports
     * @property comRestHook $comRestHook
     * @property comSendCloud $comSendCloud
     * @property comSeoUrls $comSeoUrls
     * @property comShipping $comShipping
     * @property comShop $comShop
     * @property comSitemap $comSitemap
     * @property comSpaarpunten $comSpaarpunten
     * @property comSupplier $comSupplier
     * @property comTax $comTax
     * @property comTools $comTools
     * @property comUpdate $comUpdate
     * @property comSwretail $comSwretail
     * @property comCsv $comCsv
     * @property comTag $comTag
     * @property comInvoice $comInvoice
     * @property comPagination $comPagination
     * @property comShopsUnited $comShopsUnited
     * @property comBarcode $comBarcode
     */
    class StApp {
        public static $instance;

        private $m_database_handler;
        private $m_databases;
        private $m_controllers;
        private $m_components;
        private $m_components_list;
        private $m_delegates;
        private $m_custom;
        private $m_config_file;
        private $m_blocks;
        private $m_frontEnd;
        private $m_globals;
        private $m_smarty;
        private $yieldBuffer;
        private $m_services_loaded;
        private $sessionManager;
        private $subscribers;
		
		/**
		 * @var EntityManager|null
		 */
		private ?EntityManager $entityManager;
		private \Symfony\Component\HttpFoundation\Request $requestObject;
		
		/**
		 * Singleton creator function
		 * @param bool $frontEnd
		 * @param null $databaseHandler
		 * @return StApp
		 */
		public static function instance(bool $frontEnd = true, $databaseHandler = null): StApp {
			if (!self::$instance) {
				self::$instance = new StApp($frontEnd, $databaseHandler);
				self::$instance->initialize();
			}
			
			return self::$instance;
		}
		
		private function __clone() {
		}
		
		/**
		 * Helper function for pub/sub
		 * @param string $pattern
		 * @param string $string
		 * @return bool
		 */
		protected function matchWildcard(string $pattern, string $string): bool {
			$patternLength = strlen($pattern);
			$stringLength = strlen($string);
			$patternIndex = 0;
			$stringIndex = 0;
			$prevPatternIndex = -1;
			$prevStringIndex = -1;
			
			while ($stringIndex < $stringLength) {
				if ($patternIndex < $patternLength) {
					$patternChar = $pattern[$patternIndex];
					
					if ($patternChar == '?' || $patternChar == $string[$stringIndex]) {
						$patternIndex++;
						$stringIndex++;
						continue;
					}
					
					if ($patternChar == '*') {
						$prevPatternIndex = $patternIndex;
						$prevStringIndex = $stringIndex;
						$patternIndex++;
						continue;
					}
				}
				
				if ($prevPatternIndex != -1) {
					$patternIndex = $prevPatternIndex + 1;
					$stringIndex = ++$prevStringIndex;
				} else {
					return false;
				}
			}
			
			while ($patternIndex < $patternLength && $pattern[$patternIndex] == '*') {
				$patternIndex++;
			}
			
			return $patternIndex == $patternLength && $stringIndex == $stringLength;
		}
		
		/**
		 * Haalt een lijst van doelwitten op die beschikbaar zijn in de wachtrij.
		 * @return array Een lijst van beschikbare doelwitten in de wachtrij.
		 */
		private function queueGetTargets(): array {
			return $this->getDB()->GetCol("
				SELECT DISTINCT
					`target`
				FROM `inventory_queue`
				WHERE `done`=0
				ORDER BY `target`
			");
		}
		
		/**
		 * Reroute call to another target
		 * @param $call_data
         * @param $onlySTCommands
         * @param $overridePassword
         * @return array|string[]
		 */
		private function callSlave($call_data, $onlySTCommands, $overridePassword): array {
			try {
				// set the origin for this call
				$call_data["origin"] = $this->comCore->getShopName();
				
				// determine the url to call
				if ((str_starts_with($call_data["target"], "http://")) || (str_starts_with($call_data["target"], "https://"))) {
					$callUrl = rtrim($call_data["target"], "/") . "/stApp/stub.php";
				} elseif (($shop_info = $this->comShop->getInfo($this->comShop->getIdFromName(substr($call_data["target"], 5)))) !== false) {
					$callUrl = ($shop_info["secure"] ? 'https://' : 'http://') . $shop_info["domain"] . '/stApp/stub.php';
				} else {
					throw new Exception("Target node {$call_data["target"]} not found or database error.");
				}
				
				// get correct encryption password
				if (empty($overridePassword)) {
					$password = $onlySTCommands ? $this->comShop->getApiPasswordST() : $this->comShop->getApiPassword();
				} else {
					$password = $overridePassword;
				}
				
				// json encode and crypt the call data, and call curl
				$objCurl = curl_init();
				curl_setopt($objCurl, CURLOPT_URL, $callUrl);
				curl_setopt($objCurl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($objCurl, CURLOPT_TIMEOUT, 20);
				curl_setopt($objCurl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)');
				curl_setopt($objCurl, CURLOPT_POST, true);
				curl_setopt($objCurl, CURLOPT_POSTFIELDS, http_build_query(["data" => $this->comTools->universalEncrypt(json_encode($call_data, true), "multiCatalog_{$password}")]));
				curl_setopt($objCurl, CURLOPT_SSL_VERIFYPEER, false); // only for debug purposes!!!!
				
				$output = curl_exec($objCurl);
				$http_code = curl_getinfo($objCurl, CURLINFO_HTTP_CODE);
				$error_code = curl_error($objCurl);
				curl_close($objCurl);
				
				if ($output !== false) {
					if (($http_code >= 200) && ($http_code < 300)) {
						if (empty($output)) {
							return ["result" => "error", "message" => "Empty response"];
						}
						
						$jsonDecoded = json_decode($output, true);
						
						if (json_last_error() != JSON_ERROR_NONE) {
							return ["result" => "error", "message" => "JSON error: " . json_last_error_msg()];
						}
						
						return $jsonDecoded;
					} elseif ($http_code == 302) {
						return ["result" => "error", "message" => "Failed on permanent redirect on {$call_data["target"]}"];
					} elseif (!empty($error_code)) {
						return ["result" => "error", "message" => $error_code];
					} elseif (strtolower($call_data["action"]) != "ping") {
						// if the remote host is down, queue the data unless it's a ping command or an ST command
						$this->queueAdd($call_data["target"], $onlySTCommands, $call_data, $http_code);
						return ["result" => "timeout", "message" => "Timeout for {$call_data["target"]}"];
					} else {
						return ["result" => "timeout", "message" => "Timeout for {$call_data["target"]}"];
					}
				}
				
				return ["result" => "error", "message" => "Unable to connect to '{$call_data["target"]}' (url='{$callUrl}', httpcode: {$http_code}, curl: '{$error_code}')"];
			} catch (Exception $e) {
				return ["result" => "error", "message" => $e->getMessage()];
			}
		}
		
		/**
		 * Laadt het configuratiebestand en verwerkt de instellingen om een multidimensionale configuratie-array te vormen.
		 * Deze methode leest 'configure.php', splitst namespaces en verwerkt overerving van configuratie-instellingen.
		 * @return array De geconstrueerde configuratie-array. Retourneert een lege array als het bestand niet bestaat of niet correct is geparsed.
		 */
		private function loadConfigurationFile(): array {
			// Bepaal het pad naar het configuratiebestand.
			$configFilePath = dirname(__FILE__) . "/configure.php";
			
			// Controleer of het configuratiebestand bestaat; zo niet, retourneer een lege array.
			if (!file_exists($configFilePath)) {
				return [];
			}
			
			// Parse het INI-bestand; indien parsing mislukt, retourneer een lege array.
			$parsedIni = parse_ini_file($configFilePath, true);
			
			if ($parsedIni === false) {
				return [];
			}
			
			$config = []; // Initialiseer de configuratie-array.
			
			foreach ($parsedIni as $namespace => $properties) {
				// Split de namespace op ':' om basisconfiguratie overerving te ondersteunen.
				[$name, $extends] = array_map('trim', explode(':', $namespace, 2) + [1 => '']);
				
				// Controleer of de namespace al bestaat; zo niet, initialiseer een nieuwe.
				if (!isset($config[$name])) {
					$config[$name] = [];
				}
				
				// Indien een basis namespace is opgegeven, erf de eigenschappen daarvan.
				if ($extends !== '' && isset($parsedIni[$extends])) {
					$config[$name] = $this->inheritProperties($parsedIni[$extends], $config[$name]);
				}
				
				// Overschrijf met of voeg nieuwe eigenschappen toe aan de huidige namespace.
				$config[$name] = $this->inheritProperties($properties, $config[$name]);
			}
			
			return $config;
		}
		
		/**
		 * Erft eigenschappen van een basisconfiguratie en voegt deze toe of overschrijft deze in de gegeven configuratie.
		 * @param array $properties De eigenschappen die moeten worden overgenomen of toegevoegd.
		 * @param array $baseConfig De basisconfiguratie waarin de eigenschappen moeten worden overgenomen.
		 * @return array De bijgewerkte configuratie met de overgenomen eigenschappen.
		 */
		private function inheritProperties(array $properties, array $baseConfig): array {
			foreach ($properties as $prop => $val) {
				// Zet stringwaarden "true" of "false" om naar hun booleaanse waarden en voegt ze toe aan de configuratie
				$baseConfig[$prop] = is_string($val) ? $this->convertToBoolean($val) : $val;
			}
			
			return $baseConfig;
		}
		
		/**
		 * Converteert een string waarde naar een booleaanse waarde als deze "true" of "false" is, anders
		 * retourneert het de originele waarde.
		 * @param string $value De waarde die moet worden geconverteerd.
		 * @return bool|string De geconverteerde booleaanse waarde of de originele waarde als deze niet geconverteerd kan worden.
		 */
		private function convertToBoolean(string $value): bool|string {
			if (strcasecmp($value, "true") == 0) {
				return true; // Retourneert de booleaanse waarde true als de string "true" is
			} elseif (strcasecmp($value, "false") == 0) {
				return false; // Retourneert de booleaanse waarde false als de string "false" is
			} else {
				return $value; // Retourneert de originele waarde als deze niet "true" of "false" is
			}
		}
		
		/**
		 * Returns true if the given execute/event target is a local call, false if it's a remote call
		 * @param string $target
		 * @return bool
		 */
		private function targetIsLocal(string $target): bool {
			if ($target == "st://localhost") {
				return true;
			} elseif ((isset($_SERVER["HTTP_HOST"])) && (str_starts_with($target, "http://"))) {
				return substr($target, 7) == $_SERVER["HTTP_HOST"];
			} elseif ((isset($_SERVER["HTTP_HOST"])) && (str_starts_with($target, "https://"))) {
				return substr($target, 8) == $_SERVER["HTTP_HOST"];
			} elseif (str_starts_with($target, "st://")) {
				$targetRes = $this->getDB()->qstr(substr($target, 5));
				$storedDomain = $this->getDB()->getOne("SELECT TRIM(REPLACE(`domain`, 'www.', '')) FROM `inventory_shops` WHERE `shop_name`='{$targetRes}'");
				$shopCount = $this->comShop->getShopCount();
				
				if (
					($shopCount <= 1) ||
					($targetRes == $this->comCore->getShopName()) ||
					($_SERVER["HTTP_HOST"] == rtrim($storedDomain, '/')) ||
					($storedDomain == "localhost")
				) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * StApp constructor.
		 * @param bool $frontEnd
		 * @param mixed $databaseHandler
		 */
		private function __construct(bool $frontEnd, mixed $databaseHandler = null) {
			// default timezone
			date_default_timezone_set("Europe/Amsterdam");
			
			// initialize rand function for more random numbers
			srand();
			
			// initialize internal variables
			$this->m_database_handler = $databaseHandler;
			$this->m_controllers = [];
			$this->m_components = [];
			$this->m_blocks = [];
			$this->m_delegates = [];
			$this->m_custom = [];
			$this->m_databases = [];
			$this->m_globals = [];
			$this->m_frontEnd = $frontEnd;
			$this->m_config_file = $this->loadConfigurationFile();
			$this->yieldBuffer = [];
			$this->m_services_loaded = [];
			$this->subscribers = [];
            $this->entityManager = null;
			$this->requestObject = Symfony\Component\HttpFoundation\Request::createFromGlobals();
		}
		
		/**
		 * Initialiseer stApp
		 * @return void
		 */
		public function initialize(): void {
			$documentRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . "..");
			$cacheFile = "{$documentRoot}/temp/autoloader_cache.txt";
			
			if (!file_exists($cacheFile) || (time() - filemtime($cacheFile) > 86400)) {
				$this->updateAutoloaderCache($documentRoot, $cacheFile);
			}
			
			// grab all component names
			$baseDir = dirname(__FILE__);
			$this->m_components_list = array_map(function ($e) use ($baseDir) {
				return substr($e, strlen($baseDir) + 12);
			}, glob($baseDir . "/components/*"));
			
			// Setup various elements
			$this->setupSmarty();
			$this->setupAutoloading($documentRoot);
			$this->initiateDatabase();
			$this->configureSession();
			$this->loadEventListeners();
			$this->loadComponents($documentRoot);
			
			// shutdown functie
			register_shutdown_function([$this, "shutdown"]);
		}
		
		/**
		 * Initialiseert en configureert de Smarty template engine voor het project.
		 * Deze methode stelt de basisconfiguratie in voor Smarty, zoals de directories voor templates,
		 * gecompileerde templates en cache. Ook worden specifieke plugins geregistreerd die helpen
		 * bij het modificeren van data binnen templates of het toevoegen van custom functionaliteit,
		 * zoals het converteren van strings naar snake_case of het zuiveren van HTML output.
		 * @return void
		 */
		private function setupSmarty(): void {
			// Bepaal het basispad naar de Smarty bibliotheek
			$pathToSmarty = realpath(dirname(__FILE__) . "/../");
			
			// Maak een nieuw Smarty object en configureer deze
			$this->m_smarty = new Smarty();
			$this->m_smarty->error_reporting = 1; // Zet error reporting uit
			$this->m_smarty->setTemplateDir($pathToSmarty . '/views'); // Stel de directory in voor de templates
			$this->m_smarty->setCompileDir($pathToSmarty . '/views_c'); // Stel de directory in voor de gecompileerde templates
			$this->m_smarty->setCacheDir($pathToSmarty . '/cache'); // Stel de directory in voor de cache
			
			// Registreer custom plugins voor gebruik in Smarty templates
			$this->m_smarty->registerPlugin('modifier', 'snake_case', [$this, 'snakeCaseForSmarty']); // Voor het omzetten van strings naar snake_case
			$this->m_smarty->registerPlugin('function', 'picture', 'smarty_function_picture'); // Voor het genereren van <picture> elementen
			$this->m_smarty->registerPlugin('modifier', 'purify', [$this, 'smartyHtmlPurify']); // Voor het zuiveren van HTML output
		}
		
		/**
		 * Update de cache voor de autoloader met de bestandspaden en klassenamen van PHP-bestanden.
		 * Deze methode doorloopt een lijst met directories, vindt PHP-bestanden, en slaat hun paden
		 * en namen op in een cachebestand. Dit optimaliseert het autoloading proces door het verminderen
		 * van de noodzaak om bestandssystemen te doorzoeken tijdens runtime.
		 * @param string $documentRoot Het basispad van de applicatie, gebruikt om de volledige paden naar directories te construeren.
		 * @param string $cacheFile Het pad naar het cachebestand waar de autoloader data wordt opgeslagen.
		 * @return void
		 */
		private function updateAutoloaderCache(string $documentRoot, string $cacheFile): void {
			// Definieer de lijst met directories om te doorzoeken voor PHP-bestanden
			$directories = [
				$documentRoot . '/stApp/api/',
				$documentRoot . '/stApp/api/controllers/',
				$documentRoot . '/stApp/api/controllers/v1/',
				$documentRoot . '/stApp/api/database/',
				$documentRoot . '/stApp/api/database/v1/',
				$documentRoot . '/stApp/api/models/',
				$documentRoot . '/stApp/api/models/v1/',
				$documentRoot . "/includes/classes/",
				$documentRoot . "/includes/classes/Database/",
				$documentRoot . "/includes/classes/Controllers/",
				$documentRoot . "/includes/classes/Controllers/Frontend/",
				$documentRoot . "/includes/classes/Controllers/Backend/",
			];
			
			// Voor het opslaan van de paden en klassenamen
			$autoloaderContent = [];
			
			// Doorloop elke directory en vind PHP-bestanden
			foreach ($directories as $directory) {
				foreach (new DirectoryIterator($directory) as $file) {
					if ($file->isDot() || $file->getExtension() !== 'php') {
						continue;
					}
					
					// Voeg het pad en de klassenaam van het bestand toe aan de cache data
					$autoloaderContent[] = "{$file->getPathname()}|{$file->getBasename('.php')}";
				}
			}
			
			// Schrijf de cache data naar het cachebestand
			file_put_contents($cacheFile, implode("\n", $autoloaderContent));
		}
		
		/**
		 * Registreert een autoloader functie om klassen automatisch te laden vanuit een gespecificeerde root directory.
		 * De functie maakt gebruik van een cachebestand voor het opslaan van paden naar klassen om de laadtijd te verminderen.
		 * @param string $documentRoot De root directory van waaruit klassen geladen worden.
		 */
		private function setupAutoloading(string $documentRoot): void {
			spl_autoload_register(function ($className) use ($documentRoot) {
				static $globalAutoloaderCache = null;
				$documentRoot = realpath(__DIR__ . '/../');
				
				if ($globalAutoloaderCache === null) {
					$cacheFilePath = "{$documentRoot}/temp/autoloader_cache.txt";
					$globalAutoloaderCache = file($cacheFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
				}
				
				if (str_contains($className, "\\")) {
					$this->loadClassFromPath($className, $documentRoot);
				} else {
					$this->loadClassFromCache($className, $globalAutoloaderCache);
				}
			});
		}
		
		/**
		 * Probeert een klasse te laden op basis van het namespace pad.
		 * @param string $className De volledige naam van de klasse, inclusief namespace.
		 * @param string $documentRoot De root directory voor klassen.
		 */
		private function loadClassFromPath(string $className, string $documentRoot): void {
			$classPath = str_replace("\\", DIRECTORY_SEPARATOR, $className);
			$completePath = "{$documentRoot}/stApp/{$classPath}.php";
			
			if (file_exists($completePath)) {
				include($completePath);
			}
		}
		
		/**
		 * Probeert een klasse te laden vanuit de cache.
		 * @param string $className De naam van de klasse die geladen moet worden.
		 * @param array $cache De cache waar paden naar klassen zijn opgeslagen.
		 */
		private function loadClassFromCache(string $className, array $cache): void {
			foreach ($cache as $cacheItem) {
				[$path, $cachedClassName] = explode("|", $cacheItem);
				
				if ($cachedClassName === $className) {
					include($path);
					return;
				}
			}
		}
		
		/**
		 * Initialiseert de databaseverbinding.
		 * Deze methode controleert of er al een database handler bestaat binnen de applicatie.
		 * Als deze bestaat, wordt deze gebruikt; zo niet, dan wordt een nieuwe instantie van de
		 * databaseklasse aangemaakt en toegewezen als de primaire databaseverbinding voor de applicatie.
		 * @return void
		 */
		private function initiateDatabase(): void {
			// Controleer of er al een database handler geïnitialiseerd is
			if (!is_null($this->m_database_handler)) {
				// Gebruik de bestaande database handler
				$this->m_databases["core"] = $this->m_database_handler;
			} else {
				// Maak een nieuwe instantie van de databaseklasse en wijs deze toe
				$this->m_databases["core"] = new databaseAdapter();
			}
		}
		
		/**
		 * Configureert de sessie voor de applicatie.
		 * Deze methode initialiseert de sessiemanager met de databaseverbinding,
		 * stelt een aangepaste sessieopslaghandler in, past beveiligingsinstellingen toe,
		 * en start vervolgens de sessie met specifieke instellingen afhankelijk van de context
		 * (bijvoorbeeld backend versus frontend). Ook wordt de taalinstelling gecontroleerd
		 * en zo nodig aangepast.
		 * @return void
		 */
		private function configureSession(): void {
			// Initialiseren van de sessiemanager met een databaseverbinding voor sessieopslag
			$this->sessionManager = new SessionManager($this->m_databases["core"]);
			
			// Instellen van de sessie opslaghandler om sessiegegevens in de database op te slaan
			session_set_save_handler($this->sessionManager, true);
			
			// Toepassen van beveiligingsinstellingen voor de sessie
			$this->configureSessionSecurity();
			
			// Configureren van de sessienaam voor backend-gebruik indien de frontEnd eigenschap niet gezet is
			if (!$this->m_frontEnd) {
				session_name("osCAdminID");
			}
			
			// Starten van de sessie
			session_start();
			
			// Controleren en instellen van de taalinstelling voor de sessie in een backend-context
			if (!$this->m_frontEnd) {
				// Stel de taal opnieuw in als deze niet is ingesteld of als een ongeldige waarde heeft
				if (!isset($_SESSION['language']) || in_array($_SESSION['language'], ['nl', ''])) {
					// Verwijderen van de huidige taalinstellingen indien niet geldig
					unset($_SESSION['language']);
					
					// Resetten van taal en taal_id naar een standaard of lege waarde
					$_SESSION['language'] = '';
					$_SESSION['language_id'] = '';
				}
			}
		}
		
		/**
		 * Configureert sessiebeveiliging om de veiligheid van gebruikersgegevens te waarborgen.
		 * Deze functie past de cookie parameters aan voor sessies om de veiligheid te verbeteren,
		 * behalve wanneer de aanvraag afkomstig is van localhost (127.0.0.1) voor ontwikkelingsdoeleinden.
		 * Het zet 'secure' op true om te verzekeren dat cookies alleen over HTTPS verzonden worden,
		 * en 'httponly' op true om toegang tot de cookies via JavaScript te voorkomen, wat helpt
		 * tegen cross-site scripting (XSS) aanvallen.
		 * @return void
		 */
		private function configureSessionSecurity(): void {
			// Controleer of de aanvraag van localhost komt; zo ja, keer terug zonder wijzigingen
			if ($_SERVER["REMOTE_ADDR"] === '127.0.0.1') {
				return; // Geen extra beveiligingsmaatregelen nodig voor lokale ontwikkeling
			}
			
			// Haal de huidige cookie parameters op
			$currentCookieParams = session_get_cookie_params();
			
			// Stel nieuwe cookie parameters in voor de sessie
			session_set_cookie_params([
				'lifetime' => $currentCookieParams["lifetime"], // Gebruik de bestaande levensduur
				'path'     => $currentCookieParams["path"],     // Gebruik het bestaande pad
				'domain'   => $currentCookieParams["domain"],   // Gebruik de bestaande domein
				'secure'   => true,  // Zet op true om te zorgen dat cookies alleen over HTTPS worden verzonden
				'httponly' => true   // Zet op true om toegang tot cookies via JavaScript te voorkomen
			]);
		}
		
		/**
		 * Laadt componenten vanuit de opgegeven directories.
		 * Deze functie leest de configuratie van componenten uit een associatieve array,
		 * controleert of de opgegeven directories bestaan, en laadt vervolgens alle relevante
		 * PHP-bestanden (componenten) die niet uitgesloten zijn en voldoen aan de prefix-vereisten.
		 * Voor elk geladen component wordt gecontroleerd of een `startup` methode bestaat, en zo ja,
		 * wordt deze aangeroepen. Componentinstanties worden opgeslagen in de relevante eigenschap van de klasse.
		 * @param string $documentRoot Het basispad naar de directory waar de componenten zich bevinden.
		 * @return void
		 */
		private function loadComponents(string $documentRoot): void {
			// Definieer de componenten en hun configuratie
			$components = [
				"m_blocks"    => ["dir" => "/stApp/blocks", "prefix" => "Api", "exclude" => ["ApiBase"]],
				"m_delegates" => ["dir" => "/stApp/delegates", "prefix" => "delegate", "exclude" => ["DelegateBase"]],
				"m_custom"    => ["dir" => "/StApp/custom", "prefix" => "custom", "exclude" => ["CustomBase"]],
			];
			
			// Doorloop elk componenttype en hun configuratie
			foreach ($components as $componentType => $config) {
				// Bouw het volledige pad naar de directory
				$dirPath = $documentRoot . $config['dir'];
				
				// Controleer of de directory bestaat, zo niet, sla over
				if (!is_dir($dirPath)) {
					continue; // Als de directory niet bestaat, ga verder met de volgende
				}
				
				// Maak een iterator voor bestanden in de directory
				$componentFiles = new DirectoryIterator($dirPath);
				
				// Doorloop elk bestand in de directory
				foreach ($componentFiles as $file) {
					// Sla dot-bestanden, niet-php bestanden en directories over
					if ($file->isDot() || !$file->isFile() || $file->getExtension() !== 'php') {
						continue;
					}
					
					// Sla klassen over die niet moeten worden geladen of niet beginnen met de vereiste prefix
					$className = $file->getBasename('.php');
					
					if (in_array($className, $config['exclude']) || !str_starts_with($className, $config['prefix'])) {
						continue;
					}
					
					// Sla files over die niet bestaan. Zou nooit moeten voorkomen, maar kan door git updates.
					if (!file_exists($file->getPathname())) {
						continue;
					}
					
					// Include de component class bestand
					require_once $file->getPathname();
					
					// Instantieer de componentklasse
					$componentInstance = new $className($this);
					
					// Controleer of het component een `startup` methode heeft en roep deze aan, indien aanwezig
					if (method_exists($componentInstance, 'startup')) {
						$componentInstance->startup();
					}
					
					// Sla de instantie op in de relevante eigenschap
					$this->{$componentType}[$className] = $componentInstance;
				}
			}
		}
		
		/**
		 * Laadt event listeners op basis van de configuratie.
		 * Deze functie haalt de configuratie op en controleert of er event listeners zijn gedefinieerd.
		 * Indien gedefinieerd, worden deze instanties gemaakt en toegevoegd aan de lijst van subscribers.
		 * Bestaande instanties van listeners worden hergebruikt om duplicatie te voorkomen.
		 */
		public function loadEventListeners(): void {
			// Haal de configuratie op.
			$configuration = $this->getConfiguration();
			
			// Als er geen event listeners zijn gedefinieerd, stop de uitvoering van de functie.
			if (empty($configuration["event_listener"])) {
				return;
			}
			
			// Loop door de configuratie heen
			$instantiatedListeners = [];
			
			foreach ($configuration["event_listener"] as $topic => $listeners) {
				foreach ((array)$listeners as $listenerName) {
					// Controleer of de listener al geïnstantieerd is om hergebruik mogelijk te maken.
					if (!array_key_exists($listenerName, $instantiatedListeners)) {
						$listenerNamePlusNamespace = "Services\\EventListeners\\{$listenerName}";
						$eventListener = new $listenerNamePlusNamespace(...$this->autowireClass($listenerNamePlusNamespace));
						$instantiatedListeners[$listenerName] = $eventListener;
					} else {
						$eventListener = $instantiatedListeners[$listenerName];
					}
					
					// Voeg de listener toe aan de subscribers array indien nog niet bestaand.
					$this->subscribers[$topic][] = $eventListener;
				}
			}
		}
		
		/**
		 * Deze functie zuivert HTML content met behulp van de html purifier. Dit is handig om ervoor te zorgen dat
		 * de content veilig is voor weergave door ongewenste tags of javascript te verwijderen.
		 * @param string $content De HTML-content die gezuiverd moet worden.
		 * @param string $allowed_tags Een string met tags die toegestaan zijn. Als leeg, worden standaard instellingen gebruikt.
		 * @return string De gezuiverde HTML content.
		 */
		public function smartyHtmlPurify(string $content, string $allowed_tags = ""): string {
			return $this->comTools->purifyHTML($content, $allowed_tags);
		}
		
		/**
		 * Deze functie converteert een string naar snake_case, wat nuttig is voor consistentie in benamingen,
		 * bijvoorbeeld bij het gebruik in templates. Het zet CamelCase of namen met spaties, streepjes of punten
		 * om naar snake_case.
		 * @param string $string De string die omgezet moet worden naar snake_case.
		 * @return string De omgezette string in snake_case.
		 */
		public function snakeCaseForSmarty(string $string): string {
			$snake = strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
			return str_replace([" ", '-', "."], ["_", "_", "_"], $snake);
		}
		
		/**
		 * Verkrijgt de typehints en standaardwaarden voor parameters van een specifieke methode in een klasse.
		 * Dit is nuttig voor het inspecteren van methodes op runtime, om te begrijpen welke argumenten een methode
		 * verwacht, inclusief hun types en standaardwaarden, indien aanwezig.
		 * @param string $className De volledige naam van de klasse waarvan de methode geïnspecteerd moet worden.
		 * @param string $methodName De naam van de methode om te inspecteren. Als dit leeg is, wordt de constructor geïnspecteerd.
		 * @return array Een lijst met details van elke parameter, inclusief naam, type en standaardwaarde.
		 */
		private function getMethodTypeHints(string $className, string $methodName = ""): array {
			$result = [];
			
			try {
				// Bepaal of we de constructor of een specifieke methode inspecteren.
				$reflectionClass = new ReflectionClass($className);
				$methodReflector = empty($methodName) ? $reflectionClass->getConstructor() : $reflectionClass->getMethod($methodName);
				
				if ($methodReflector) {
					foreach ($methodReflector->getParameters() as $parameter) {
						// Bepaal het type van de parameter, indien beschikbaar.
						$type = $parameter->hasType() ? $parameter->getType()->getName() : "";
						
						// Bepaal de standaardwaarde van de parameter, indien aanwezig.
						$defaultValue = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
						
						// Voeg de data toe aan het resultaat
						$result[] = [
							'name'          => $parameter->getName(),
							'type'          => $type,
							'default_value' => $defaultValue
						];
					}
				}
			} catch (ReflectionException $e) {
			}
			
			return $result;
		}
		
		/**
		 * Use reflection to autowire service classes into the constructor of an object
		 * @param string $className
		 * @param string $methodName
		 * @param array $matchingVariables
		 * @return array
		 * @throws ReflectionException
		 */
		public function autowireClass(string $className, string $methodName = "", array $matchingVariables = []): array {
			$passArguments = [];
			$methodTypeHints = $this->getMethodTypeHints($className, $methodName);
			
			foreach ($methodTypeHints as $typeHint) {
				if (!empty($typeHint["type"])) {
					if (in_array($typeHint["type"], ["array", "string", "integer", "int", "float", "double", "boolean", "bool"])) {
						if (isset($matchingVariables[$typeHint["name"]])) {
							$passArguments[] = $matchingVariables[$typeHint["name"]];
						} else {
							$passArguments[] = $typeHint["default"];
						}
					} elseif (stripos($typeHint["type"], "stApp") !== false) {
						$passArguments[] = $this;
					} elseif (stripos($typeHint["type"], "clsDB") !== false) {
						$passArguments[] = $this->getDB();
					} elseif (stripos($typeHint["type"], "Smarty") !== false) {
						$passArguments[] = $this->getSmarty();
					} elseif (stripos($typeHint["type"], "Services\\EntityManager\\EntityManager") !== false) {
						$passArguments[] = $this->getEntityManager();
					} elseif (stripos($typeHint["type"], "Symfony\\Component\\HttpFoundation\\Request") !== false) {
						$passArguments[] = $this->requestObject;
					} elseif ($this->componentExists($typeHint["type"])) {
						$componentName = $typeHint["type"];
						$path_to_component = dirname(__FILE__) . "/components/{$componentName}.php";
						include_once($path_to_component);
						$component = new $componentName(... $this->autowireClass($componentName));
						
						if (method_exists($component, "startup")) {
							$component->startup();
						}
						
						$passArguments[] = $component;
                    } elseif ((str_starts_with($typeHint["type"], "Services\\Entity\\")) && $this->getEntityManager()->entityExists($typeHint["type"])) {
						$passArguments[] = $this->getEntityManager()->find($typeHint["type"], $matchingVariables[$typeHint["name"]]);
					} else {
						$passArguments[] = $this->getContainer($typeHint["type"]);
					}
				} elseif (isset($matchingVariables[$typeHint["name"]])) {
					$passArguments[] = $matchingVariables[$typeHint["name"]];
				} else {
					$passArguments[] = $typeHint["default"];
				}
			}
			
			return $passArguments;
		}
		
		/**
		 * Fetches a container
		 * @param string $serviceName
		 * @return mixed
		 * @throws Exception
		 */
		public function getContainer(string $serviceName): mixed {
			if (!isset($this->m_services_loaded[$serviceName])) {
				$this->m_services_loaded[$serviceName] = new $serviceName(... $this->autowireClass($serviceName));
				return $this->m_services_loaded[$serviceName];
			}
			
			return $this->m_services_loaded[$serviceName];
		}
		
		/**
		 * Returns true if the given component exists in the components folder, false otherwise
		 * @param string $componentName
		 * @return bool
		 */
		public function componentExists(string $componentName): bool {
			$componentFilename = $componentName . ".php";
			
			foreach ($this->m_components_list as $component) {
				if (strcasecmp($component, $componentFilename) == 0) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Returns true if the given controller exists in the components folder, false otherwise
		 * @param string $controllerName
		 * @return bool
		 */
		public function controllerExists(string $controllerName): bool {
			$controllerName = ucfirst($controllerName);
			
			$path_to_component = dirname(__FILE__) . "/controllers/controller{$controllerName}.php";
			
			return file_exists($path_to_component);
		}
		
		/**
		 * Returns true if the script is called from the cli, false if not
		 * @return bool
		 */
		public function isCli(): bool {
			if (defined('STDIN')) {
				return true;
			}
			
			if (php_sapi_name() === 'cli') {
				return true;
			}
			
			if (array_key_exists('SHELL', $_ENV)) {
				return true;
			}
			
			if (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0) {
				return true;
			}
			
			if (!array_key_exists('REQUEST_METHOD', $_SERVER)) {
				return true;
			}
			
			return false;
		}
		
		/**
		 * Returns true if the current call is a GET call
		 * @return bool
		 */
		public function isGet(): bool {
			return ($_SERVER['REQUEST_METHOD'] === 'GET');
		}
		
		/**
		 * Returns true if the current call is a POST call
		 * @return bool
		 */
		public function isPost(): bool {
			return ($_SERVER['REQUEST_METHOD'] === 'POST');
		}
		
		/**
		 * Returns true if the current call is a OPTIONS call
		 * @return bool
		 */
		public function isOptions(): bool {
			return ($_SERVER['REQUEST_METHOD'] === 'OPTIONS');
		}
		
		/**
		 * Returns true if the current call is an AJAX call
		 * Note that this can be spoofed
		 * @return bool
		 */
		public function isAjax(): bool {
			return ((isset($_SERVER['HTTP_X_REQUESTED_WITH'])) && ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest'));
		}
		
		/**
		 * Returns true if the current call is a DELETE call
		 * @return bool
		 */
		public function isDelete(): bool {
			return ($_SERVER['REQUEST_METHOD'] === 'DELETE');
		}
		
		/**
		 * Returns true if the current call is a PUT call
		 * When using PUT, it is assumed that you are sending the complete entity, and
		 * that complete entity replaces any existing entity at that URI. [...]
		 * PUT handles it by replacing the entire entity, while PATCH only updates the fields
		 * that were supplied, leaving the others alone.
		 * @url https://stackoverflow.com/questions/28459418/rest-api-put-vs-patch-with-real-life-examples
		 * @return bool
		 */
		public function isPut(): bool {
			return ($_SERVER['REQUEST_METHOD'] === 'PUT');
		}
		
		/**
		 * Returns true if the current call is a PATCH call
		 * PATCH only updates the fields that were supplied, leaving the others alone.
		 * @url https://stackoverflow.com/questions/28459418/rest-api-put-vs-patch-with-real-life-examples
		 * @return bool
		 */
		public function isPatch(): bool {
			return ($_SERVER['REQUEST_METHOD'] === 'PATCH');
		}
		
		/**
		 * Returns the content body of the document
		 * @return false|string
		 */
		public function getContentBody(): false|string {
			return $this->requestObject->getContent();
		}
		
		/**
		 * Ping a target to see if it's up
		 * @param string $target
		 * @return array
		 */
		public function ping(string $target): array {
			$call_data = [];
			$call_data["action"] = "ping";
			$call_data["target"] = $target;
			return $this->execute($call_data);
		}
		
		/**
		 * Magic method for dynamic loading of components and previously set globals
		 * @param string $name
		 * @return mixed|null
		 * @throws Exception
		 */
		public function __get(string $name) {
			$firstThreeCharacters = substr($name, 0, 3);
			
			if ($firstThreeCharacters == "com") {
				$methodWithoutCom = ucfirst(substr($name, 3));
				$method = "get{$methodWithoutCom}";
				return ($method == "getDB") ? $this->getDB() : $this->__call($method, []);
			} elseif ($firstThreeCharacters == "cnt") {
				return $this->__call($name, []);
			} elseif ($firstThreeCharacters == "ext") {
				return $this->__call($name, []);
			} elseif (isset($this->m_globals[$name])) {
				return $this->m_globals[$name];
			} else {
				return null;
			}
		}
		
		public function __set($name, $value) {
			$this->m_globals[$name] = $value;
		}
		
		public function __isset($name): bool {
			return isset($this->m_globals[$name]);
		}
		
		public function __unset($name) {
			unset($this->m_globals[$name]);
		}
		
		/**
		 * Handler for calling controllers and components
		 * @param string $name
		 * @param array $arguments
		 * @return mixed
		 * @throws Exception
		 */
		public function __call(string $name, array $arguments) {
			switch (substr($name, 0, 3)) {
				case "cnt" :
					$controllerName = "controller" . ucfirst(substr($name, 3));
					
					if (!isset($this->m_controllers[$controllerName])) {
						$path_to_controller = dirname(__FILE__) . "/controllers/{$controllerName}.php";
						
						include_once($path_to_controller);
						
						$this->m_controllers[$controllerName] = new $controllerName($this);
					}
					
					return $this->m_controllers[$controllerName];
				
				case "get" :
					$componentName = "com" . ucfirst(substr($name, 3));
					
					if (!isset($this->m_components[$componentName])) {
						$path_to_component = dirname(__FILE__) . "/components/{$componentName}.php";
						
						include_once($path_to_component);
						
						$component = new $componentName($this);
						
						if (method_exists($component, "startup")) {
							$component->startup();
						}
						
						$this->m_components[$componentName] = $component;
					}
					
					return $this->m_components[$componentName];
			}
			
			throw new Exception("Dynamic component {$name} does not exist");
		}
		
		/**
		 * Garbage collection
		 */
		public function shutdown(): void {
			foreach (array_merge($this->m_blocks, $this->m_components, $this->m_delegates) as $ac) {
				if (method_exists($ac, "garbageCollect")) {
					$ac->GarbageCollect();
				}
			}
		}
		
		/**
		 * Returns stApp's configuration file contents
		 * @return array
		 */
		public function getConfiguration(): array {
			return $this->m_config_file;
		}
		
		/**
		 * Returns a link to the database.
		 * @return databaseAdapter|null
		 */
		public function getDB(): ?databaseAdapter {
			return $this->m_databases["core"];
		}
		
		/**
		 * @return EntityManager
		 * @throws Exception
		 */
		public function getEntityManager(): EntityManager {
			if (is_null($this->entityManager)) {
				$this->entityManager = new EntityManager($this->getDB(), $this);
			}
			
			return $this->entityManager;
		}
		
		/**
		 * Add an entry to the timeout/handling queue
		 * @param string $target
		 * @param string $stCommand
		 * @param array $data
		 * @param int $httpCode
		 * @return bool|int
		 */
		public function queueAdd(string $target, string $stCommand, array $data, int $httpCode): bool|int {
			// Vroege terugkeer indien target leeg is of shopName niet bestaat
			if (empty($target) || !$this->comShop->shopNameExists($target)) {
				return false;
			}
			
			// Uitvoeren van de SQL-query
			$this->getDB()->Execute("
                INSERT INTO `inventory_queue` SET
                    `target`=:target,
                    `st_command`=:st_command,
                    `data`=:data,
                    `date`=:date,
                    `http_code`=:http_code,
                    `done`=:done
            ", [
				'target'     => $target,
				'st_command' => $stCommand ? 1 : 0,
				'data'       => json_encode($data),
				'date'       => date("Y-m-d H:i:s"),
				'http_code'  => $httpCode,
				'done'       => 0
			]);
			
			// Retourneer insert_id bij succes, anders false (consistentie met return types)
			return $this->getDB()->insert_id() ?: false;
		}
		
		/**
		 * Run the queue. All saved up remote API commands will be processed as soon as a shop becomes
		 * available again.
		 * @return array
		 */
		public function runQueue($type = 0): array {
			$log = [];
			$queueTargets = $this->queueGetTargets();
			
			foreach ($queueTargets as $target) {
				$targetRes = $this->getDB()->qstr($target);
				$ping_result = $this->ping($target);
				
				if ($ping_result["result"] == "success") {
					if (($rs = $this->getDB()->Execute("
                        SELECT
                            `id`,
                            `st_command`,
                            `data`
                        FROM `inventory_queue`
                        WHERE `type`=:type AND
                              `target`=:target
                        ORDER BY `date`
                    ", [
						'type'   => $type,
						'target' => $target,
					]))) {
						$done = [];
						
						while (($row = $this->getDB()->FetchRow($rs))) {
							$result = $this->execute(json_decode($row["data"], true), $row["st_command"]);
							
							if ($result["result"] != "timeout") {
								$done[] = $row["id"];
							}
						}
						
						if (!empty($done)) {
							$this->getDB()->Execute("
                                DELETE
                                FROM `inventory_queue`
                                WHERE `target`='{$targetRes}' AND
                                      `id` IN (" . implode(",", $done) . ")
                            ");
						}
						
						$log[$target] = "success";
					} else {
						$log[$target] = "error";
					}
				} else {
					$log[$target] = "timeout";
				}
			}
			
			return $log;
		}
		
		/**
		 * Handles an API command (internal or remote)
		 * @param $decrypted_data
		 * @return array|null
		 */
		public function execute($decrypted_data, $onlySTCommands = false, $overridePassword = ""): ?array {
			// set origin if it's not there
			if (!isset($decrypted_data["origin"])) {
				$decrypted_data["origin"] = $this->comCore->getShopName();
			}
			
			// check and extend target
			if (!isset($decrypted_data["target"])) {
				$decrypted_data["target"] = "st://" . $this->comCore->getShopName();
			} elseif ((!str_starts_with($decrypted_data["target"], "http://")) && (!str_starts_with($decrypted_data["target"], "https://"))) {
				if ((!str_starts_with($decrypted_data["target"], "st://"))) {
					if (!str_contains($decrypted_data["target"], "://")) {
						$decrypted_data["target"] = "st://" . $decrypted_data["target"];
					} else {
						return ["result" => "error", "message" => "Only st://, http:// and https:// streams allowed as target"];
					}
				}
			}
			
			// walk over all API components and find the one that handles the action.
			// if it exists, call the action. If the target is not this node call it
			// through CURL
			if (isset($decrypted_data["action"])) {
				$controller = "";
				$originalAction = $decrypted_data["action"];
				
				if (str_contains($decrypted_data["action"], ":")) {
					$controller = $onlySTCommands ? "ApiST" : "Api" . ucfirst(substr($decrypted_data["action"], 0, strpos($decrypted_data["action"], ":")));
					$decrypted_data["action"] = substr($decrypted_data["action"], strpos($decrypted_data["action"], ":") + 1);
				} elseif ($onlySTCommands) {
					$controller = "ApiST";
				}
				
				try {
					if ($this->targetIsLocal($decrypted_data["target"])) {
						foreach ($this->m_blocks as $component) {
							if ((empty($controller)) || (strcasecmp($controller, get_class($component)) == 0)) {
								if (($result = $component->execute($decrypted_data)) !== false) {
									if ($this->comShop->apiDebug()) {
										$this->comLogAction->logCall($decrypted_data, $result);
									}
									
									return $result;
								}
							}
						}
						
						$result = ["result" => "error", "message" => "action '{$originalAction}' not found on '{$decrypted_data["target"]}'"];
					} else {
						$result = $this->callSlave($decrypted_data, $onlySTCommands, $overridePassword);
					}
					
					
					if ($this->comShop->apiDebug()) {
						$this->comLogAction->logCall($decrypted_data, $result);
					}
					
					return $result;
				} catch (Exception $e) {
					return ["result" => "error", "message" => $e->getMessage()];
				}
			} else {
				return ["result" => "error", "message" => "Missing action"];
			}
		}
		
		/**
		 * Returns the smarty object
		 * @return Smarty
		 */
		public function getSmarty(): Smarty {
			return $this->m_smarty;
		}
		
		/**
		 * Returns a new domPDF object
		 * @return DOMPDF
		 */
		public function getDomPdf(): DOMPDF {
			return new DOMPDF();
		}
		
		/**
		 * Returns true if stApp is called for the frontend, or false if for the backend
		 * @return bool
		 */
		function isFrontEnd(): bool {
			return $this->m_frontEnd;
		}
		
		/**
		 * Calls the on$eventName method in all delegates
		 * For example if $eventName is 'test', the method onTest is called.
		 * This function take a variable number of arguments.
		 * @param $eventName
		 * @param ...
		 */
		public function dispatch($eventName): void {
			foreach (array_merge($this->m_blocks, $this->m_delegates) as $delegate) {
				if (method_exists($delegate, "on" . ucfirst($eventName))) {
					call_user_func_array([$delegate, "on" . ucfirst($eventName)], array_slice(func_get_args(), 1));
				}
			}
		}
		
		/**
		 * Fetch a template and store it in a variable
		 * @param string $templateName
		 * @throws SmartyException
		 */
		public function deferTemplate(string $templateName, $container = "default") {
			if ($this->getSmarty()->template_exists($templateName)) {
				$this->yieldBuffer[$container][] = $this->getSmarty()->fetch($templateName);
			}
		}
		
		/**
		 * cronDeamon handler
		 */
		public function runCron($cronParameter = null): void {
			$timeStamp = time();
			$cronData = [];
			
			// get all due crons
			if ((empty($cronParameter))) {
				$sql = "SELECT `id`, `enabled`, `cron`, `action`, `last_run`, `next_run` FROM `st_cron` WHERE `enabled`=1 AND `next_run` <= {$timeStamp}";
			} elseif (strpos($cronParameter, ":")) {
				$cronId = substr($cronParameter, 0, strpos($cronParameter, ":"));
				$parameters = explode("##", substr($cronParameter, strpos($cronParameter, ":") + 1));
				
				foreach ($parameters as $i => $parameter) {
					if ($i == 0) {
						$output = "REPLACE(`action`, '\${$i}', '" . $this->getDB()->qstr($parameters[$i]) . "')";
					} else {
						$output = "REPLACE({$output}, '\${$i}', '" . $this->getDB()->qstr($parameters[$i]) . "')";
					}
				}
				
				$sql = "SELECT `id`, `enabled`, `cron`, {$output} as `action`, `last_run`, `next_run` FROM `st_cron` WHERE `id`={$cronId}";
			} else {
				$sql = "SELECT `id`, `enabled`, `cron`, `action`, `last_run`, `next_run` FROM `st_cron` WHERE `id`={$cronData}";
			}
			
			if (($rs = $this->getDB()->Execute($sql))) {
				if ($this->getDB()->RecordCount($rs) > 0) {
					while (($row = $this->getDB()->FetchRow($rs))) {
						$cronData[] = $row;
					}
				}
			}
			
			// process crons
			if (!empty($cronData)) {
				$shopName = $this->comCore->getShopName();
				
				foreach ($cronData as $row) {
					$cron = Cron\CronExpression::factory($row["cron"]);
					$nextRun = $cron->getNextRunDate()->getTimestamp();
					$actionData = json_decode($row["action"], true);
					
					echo json_encode(["call" => "st_start:{$actionData["action"]}", "shop" => $shopName]) . "\n";
					
					$resultData = $this->execute($actionData);
					
					if (is_array($resultData)) {
						$result = $resultData;
						$result["shop"] = $shopName;
						$result["call"] = $actionData["action"];
						$result["result"] = !empty($resultData["result"]) ? $resultData["result"] : "success";
					} else {
						$result = [
							"shop"    => $shopName,
							"call"    => $actionData["action"],
							"result"  => "success",
							"message" => $resultData
						];
					}
					
                    $this->getDB()->Execute("
                        UPDATE `st_cron` SET
                            `last_run`=:last_run,
                            `next_run`=:next_run
                        WHERE `id`=:id
                    ", [
                        'last_run' => $timeStamp,
                        'next_run' => $nextRun,
                        'id'       => $row["id"],
                    ]);
					
					echo json_encode($result) . "\n";
					echo json_encode(["call" => "st_end:{$actionData["action"]}", "shop" => $shopName]) . "\n";
				}
			}
		}
		
		/**
		 * Publiceert een event naar alle subscribers die matchen met het opgegeven topic.
		 * Deze methode implementeert een pub/sub systeem vergelijkbaar met postal.js, waarbij
		 * listeners (subscribers) kunnen abonneren op events (topics) en worden genotificeerd
		 * wanneer een event wordt gepubliceerd.
		 * @param string $topic Het topic waarop het event wordt gepubliceerd.
		 * @param array $data Een array met de data die gepubliceerd wordt.
		 * @return array
		 */
		public function publishEvent(string $topic, array $data): array {
			$cumulativeData = [];

			foreach ($this->subscribers as $pattern => $eventListeners) {
				if ($this->matchWildcard($pattern, $topic)) {
					foreach ($eventListeners as $eventListener) {
						$eventListener->handle($topic, $data, $cumulativeData);
					}
				}
			}

			return $cumulativeData;
		}
	}