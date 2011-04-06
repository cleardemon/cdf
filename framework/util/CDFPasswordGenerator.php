<?php
	/**
	 * Generates a random - potentially memorable - password.
	 * Based on code I originally wrote back in 2007 in C#, which pulled theories from a now
	 * defunct 2004 website (makepassword.com).
	 * @author Bart King
	 * @license Public domain
	 */
	final class CDFPasswordGenerator
	{
		/*
		 * Some notes about how this works
		 *
		 * The original theory behind this was to create a simple, random password that a human
		 * could potentially remember. When a computer picks a random number (or letter), they
		 * are done in such a way that a normal person typically will not be able to read or remember
		 * easily.
		 *
		 * Of course that is the nature of randomisation, but this generates a password which sacrifices
		 * more cryptographically secure letters for patterns that a human may actually remember,
		 * using collections of letters - diphthongs, consonant pairs, and so on - that seem to be
		 * more readable or familiar, with the goal of making the human read it comfortably.
		 *
		 * End result is a word that isn't really a word, but follows the same properties as an actual word.
		 * Think how the pseudo-Latin "Lorem ipsum" text works for design, but for passwords.  It's not
		 * 100% secure, but that isn't the goal, and all passwords should be changed by the user anyway...
		 */

		private $_prefixes;
		private $_prefixes_length;
		private $_diphthongs;
		private $_diphthongs_length;
		private $_consonantpairs;
		private $_consonantpairs_length;
		private $_postfixes;
		private $_postfixes_length;
		private $_vowels;
		private $_vowels_length;
		private $_consonants;
		private $_consonants_length;

		public function __construct()
		{
			$this->_prefixes = array(
				'ab', 'ac', 'acr', 'acl', 'ad', 'adr', 'ah', 'ar', 'aw', 'ay', 'br', 'bl', 'cl', 'cr', 'ch',
				'dr', 'dw', 'en', 'ey', 'in', 'im', 'iy', 'oy', 'och', 'on', 'qu', 'sl', 'sh', 'sw', 'tr', 'th',
				'thr', 'un', 'st', 'str', 'kn'
			);
			$this->_prefixes_length = count($this->_prefixes) - 1;
			$this->_diphthongs = array(
				'ae', 'au', 'ea', 'ou', 'ei', 'ie', 'ia', 'ee', 'oo', 'eo', 'io'
			);
			$this->_diphthongs_length = count($this->_diphthongs) - 1;
			$this->_consonantpairs = array(
				'bb', 'bl', 'br', 'ck', 'cr', 'ch', 'dd', 'dr', 'gh', 'gr', 'gn', 'gg', 'lb', 'ld', 'lk', 'lp',
				'mb', 'mm', 'nc', 'nch', 'nd', 'ng', 'nn', 'nt', 'pp', 'pl', 'pr', 'rr', 'rch', 'rs', 'rsh', 'rt',
				'sh', 'th', 'tt', 'st', 'str'
			);
			$this->_consonantpairs_length = count($this->_consonantpairs) - 1;
			$this->_postfixes = array(
				'able', 'act', 'am', 'ams', 'ect', 'ed', 'edge', 'en', 'er', 'ful', 'ia', 'ier', 'ies', 'illy',
				'im', 'ing', 'ium', 'is', 'less', 'or', 'up', 'ups', 'y', 'igle', 'ogle', 'agle', 'ist', 'est'
			);
			$this->_postfixes_length = count($this->_postfixes) - 1;
			$this->_vowels = array( 'a', 'e', 'i', 'o', 'u' );
			$this->_vowels_length = count($this->_vowels) - 1;
			$this->_consonants = array(
				'b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z'
			);
			$this->_consonants_length = count($this->_consonants) - 1;
		}

		//
		// Randomisation
		//

		private function getRandom($max)
		{
			return mt_rand(0, $max);
		}

		// returns true if lucky old chance gives us odds of 8-1
		private function isGoodChance()
		{
			return $this->getRandom(10) > 7 ? true : false;
		}

		private function getConsonant($single, $caps)
		{
			if($caps) // if using capitals, choose a single consonant, making it upper-case
				return strtoupper($this->_consonants[$this->getRandom($this->_consonants_length)]);

			// if we have good chance and not a single letter, use a consonant pair, otherwise a single consonant
			return $this->isGoodChance() && !$single ?
				$this->_consonantpairs[$this->getRandom($this->_consonantpairs_length)] :
				$this->_consonants[$this->getRandom($this->_consonants_length)];
		}

		private function getVowel()
		{
			// if good chance, use a diphthong
			return $this->isGoodChance() ?
				$this->_diphthongs[$this->getRandom($this->_diphthongs_length)] :
				$this->_vowels[$this->getRandom($this->_vowels_length)];
		}

		private function getPrefix($caps)
		{
			// if good chance, use a prefix, otherwise a single consonant
			return $this->isGoodChance() ?
				$this->_prefixes[$this->getRandom($this->_prefixes_length)] :
				$this->getConsonant(true, $caps);
		}

		/**
		 * Generates a new password.
		 * @param int $length Maximum length of the password. The returned phrase will be equal to this length. Zero to not care.
		 * @param bool $caps If true, capitals may be present in the word.
		 * @param bool $number If true, appends a random number between 10 and 99 at the end. Note that this will be in addition to the length.
		 * @return string
		 */
		public function generate($length = 0, $caps = false, $number = false)
		{
			// make up a word
			$word =
				$this->getPrefix($caps) .
				$this->getVowel() .
				$this->getConsonant(false, $caps) .
				$this->_postfixes[$this->getRandom($this->_postfixes_length)];

			// truncate it to the length
			if($length > 0)
			{
				// if word is too short than the required length, make another word and stick it onto the end.
				if(strlen($word) < $length)
					$word .= $this->generate(strlen($word) - $length, $caps);
				// if the word is too long, trim it down.
				if(strlen($word) > $length)
					$word = substr($word, 0, $length);
			}

			if($number == true)
				$word .= mt_rand(10, 99);

			return $word;
		}
	}
