<?php
	class XORCipher {

		public static function cipher($text, $key) {
	    	$key = array_map('ord', str_split($key));
	    	$plaintext = array_map('ord', str_split($text));
	    	$keysize = count($key);
	    	$input_size = count($plaintext);
	    	$result = "";
	    	
			for ($i = 0; $i < $input_size; $i++) $result .= chr($plaintext[$i] ^ $key[$i % $keysize]);
	    
	    	return $result;
		}

		private static function text2ascii($text) {
			return array_map('ord', str_split($text));
		}
	}
?>