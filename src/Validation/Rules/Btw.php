<?php
	
	namespace Services\Validation\Rules;
	
	class Btw implements \Services\Validation\ValidationInterface {
		
		protected $conditions;
		protected $error;
		protected $m_vat_patterns;
		protected $m_country_prefixes;
		
		/**
		 * Email constructor
		 * @param array $conditions
		 */
		public function __construct(array $conditions = []) {
			$this->conditions = $conditions;
			$this->error = "";
			
			/**
			 * Country prefixes for VAT numbers
			 */
			$this->m_country_prefixes = [
				"BE", "BG", "CY", "DK", "DE", "EE", "FI", "FR", "EL", "HU", "IE", "IT", "HR", "LV",
				"LT", "LU", "MT", "NL", "AT", "PL", "PT", "RO", "SI", "SK", "ES", "CZ", "GB", "SE"
			];
			
			/**
			 * Patterns used to validate VAT numbers
			 * @url https://github.com/ibericode/vat/blob/master/src/Validator.php
			 */
			$this->m_vat_patterns = [
				'AT' => 'U[A-Z\d]{8}',
				'BE' => '(0\d{9}|\d{10})',
				'BG' => '\d{9,10}',
				'CY' => '\d{8}[A-Z]',
				'CZ' => '\d{8,10}',
				'DE' => '\d{9}',
				'DK' => '(\d{2} ?){3}\d{2}',
				'EE' => '\d{9}',
				'EL' => '\d{9}',
				'ES' => '([A-Z]\d{7}[A-Z]|\d{8}[A-Z]|[A-Z]\d{8})',
				'SP' => '([A-Z]\d{7}[A-Z]|\d{8}[A-Z]|[A-Z]\d{8})',
				'FI' => '\d{8}',
				'FR' => '[A-Z\d]{2}\d{9}',
				'GB' => '(\d{9}|\d{12}|(GD|HA)\d{3})',
				'HR' => '\d{11}',
				'HU' => '\d{8}',
				'IE' => '([A-Z\d]{8}|[A-Z\d]{9})',
				'IT' => '\d{11}',
				'LT' => '(\d{9}|\d{12})',
				'LU' => '\d{8}',
				'LV' => '\d{11}',
				'MT' => '\d{8}',
				'NL' => '\d{9}B\d{2}',
				'PL' => '\d{10}',
				'PT' => '\d{9}',
				'RO' => '\d{2,10}',
				'SE' => '\d{12}',
				'SI' => '\d{8}',
				'SK' => '\d{10}'
			];
		}
		
		/**
		 * Validate a VAT number format. This does not check whether the VAT number was really issued.
		 * @url https://github.com/ibericode/vat/blob/master/src/Validator.php
		 * @param string $vatNumber
		 * @return boolean
		 */
		protected function validateVatNumberFormat(string $normalisedVatNo): bool {
			// VAT number is not long enough. It can't be valid
			if (strlen($normalisedVatNo) <= 5) {
				return false;
			}
			
			// detect if a country prefix was added
			$country = substr($normalisedVatNo, 0, 2);
			
			// no country code given. plug the iso code
			if (is_numeric($country)) {
				$country = $this->conditions['iso2'] ?? "NL";
				$number = $normalisedVatNo;
			} else {
				$number = substr($normalisedVatNo, 2);
			}
			
			// if we don't have a pattern for this country, assume the vat number is valid
			// it will be checked against VIES anyway.
			if (!isset($this->m_vat_patterns[$country])) {
				return true;
			}
			
			// check pattern
			return preg_match('/^' . $this->m_vat_patterns[$country] . '$/', $number) > 0;
		}
		
		/**
		 * Returns the European Union VIES data attached to the given vat number
		 * If an error occurred, false is returned
		 * @param string $vatNumber
		 * @return array
		 */
		protected function getVatVIESData(string $vatNumber): array {
			try {
				// check integral validity
				$normalisedVatNo = strtoupper(preg_replace("/[^a-zA-Z0-9]+/", "", $vatNumber));
				
				if (!$this->validateVatNumberFormat($normalisedVatNo)) {
					return ['vatNumber' => $normalisedVatNo, 'valid' => false];
				}
				
				// call VIES database to check actual validity
				if (in_array(substr($normalisedVatNo, 0, 2), $this->m_country_prefixes)) {
					$countryPrefix = substr($normalisedVatNo, 0, 2);
					$vatNumber = substr($normalisedVatNo, 2);
				} else {
					$countryPrefix = "NL";
					$vatNumber = $normalisedVatNo;
				}
				
				$client = new \SoapClient("http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl");
				
				$result = $client->checkVat([
					'countryCode' => $countryPrefix,
					'vatNumber'   => $vatNumber
				]);
				
				if (is_object($result) && property_exists($result, "valid")) {
					return (array)$result;
				}
				
				return ['vatNumber' => $normalisedVatNo, 'valid' => false];
			} catch (\Exception $e) {
				return ['vatNumber' => $normalisedVatNo, 'valid' => true, 'faultMessage' => $e->getMessage()];
			}
		}
		
		/**
		 * Returns the conditions used in this Rule
		 * @return array
		 */
		public function getConditions() : array {
			return $this->conditions;
		}
		
		public function validate($value): bool {
			// no value, nothing to check
            if (($value === "") || is_null($value)) {
                return true;
            }
			
			// check VIES
			$viesData = $this->getVatVIESData($value);
			return $viesData["valid"];
		}
		
		public function getError(): string {
			if (!isset($this->conditions["message"])) {
				return "This value does not meet the criteria for a valid vat number.";
			}
			
			return $this->conditions["message"];
		}
	}