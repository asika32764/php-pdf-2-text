<?php
/**
 * Simple PDF to Text class.
 *
 * @license  MIT
 */

namespace Asika;

/**
 * Simple PDF to Text class.
 *
 * This is a free software and based on SilverStripe "class.pdf2text.php"
 *
 * @see  https://code.google.com/p/lucene-silverstripe-plugin/source/browse/trunk/thirdparty/class.pdf2text.php?r=19
 */
class Pdf2text
{
	/**
	 * @var  int
	 */
	private $multibyte = 4;

	/**
	 * ENT_COMPAT (double-quotes), ENT_QUOTES (Both), ENT_NOQUOTES (None)
	 *
	 * @var  int
	 */
	private $convertquotes = ENT_QUOTES;

	/**
	 * @var  bool
	 */
	private $showprogress = false;

	/**
	 * Property filename.
	 *
	 * @var  string
	 */
	private $filename = '';

	/**
	 * Property decodedtext.
	 *
	 * @var  string
	 */
	private $decodedtext = '';

	/**
	 * Set file name.
	 *
	 * @deprecated Use "decode" method instead
	 *
	 * @param string $filename
	 *
	 * @return  void
	 */
	public function setFilename($filename)
	{
		// Reset
		$this->decodedtext = '';
		$this->filename    = $filename;
	}

	/**
	 * Get output text.
	 *
	 * @deprecated Use "decode" method instead
	 *
	 * @param boolean $echo True to echo it.
	 *
	 * @return  string
	 */
	public function output($echo = false)
	{
		if ($echo)
		{
			echo $this->decodedtext;
		}
		else
		{
			return $this->decodedtext;
		}
	}

	/**
	 * Using unicode.
	 *
	 * @deprecated Use "decode" method instead
	 *
	 * @param boolean $input True or not to use unicode.
	 *
	 * @return  void
	 */
	public function setUnicode($input)
	{
		// 4 for unicode. But 2 should work in most cases just fine
		if ($input)
		{
			$this->multibyte = 4;
		}
		else
		{
			$this->multibyte = 2;
		}
	}

	/**
	 * Method to set property showprogress
	 *
	 * @deprecated Use "decode" method instead
	 *
	 * @param   boolean $showprogress
	 *
	 * @return  static  Return self to support chaining.
	 */
	public function showProgress($showprogress)
	{
		$this->showprogress = $showprogress;

		return $this;
	}

	/**
	 * Method to set property convertquotes
	 *
	 * @deprecated Use "decode" method instead
	 *
	 * @param   int $convertquotes
	 *
	 * @return  static  Return self to support chaining.
	 */
	public function convertQuotes($convertquotes)
	{
		$this->convertquotes = $convertquotes;

		return $this;
	}

	/**
	 * Save decode options
	 *
	 * @param string $fileName
	 * @param int    $convertQuotes ENT_COMPAT (double-quotes), ENT_QUOTES (Both), ENT_NOQUOTES (None)
	 * @param bool   $showProgress  TRUE if you have problems with time-out
	 * @param bool   $multiByteUnicode
	 */
	public function saveOptions($fileName, $convertQuotes, $showProgress, $multiByteUnicode)
	{
		$this->convertquotes = $convertQuotes;
		$this->showprogress  = $showProgress;
		$this->multibyte     = $multiByteUnicode ? 4 : 2;
		$this->filename      = $fileName;
	}

	/**
	 * Decode PDF file
	 *
	 * @param string $fileName
	 * @param int    $convertQuotes ENT_COMPAT (double-quotes), ENT_QUOTES (Both), ENT_NOQUOTES (None)
	 * @param bool   $showProgress  TRUE if you have problems with time-out
	 * @param bool   $multiByteUnicode
	 *
	 * @return string
	 */
	public function decode($fileName, $convertQuotes = ENT_QUOTES, $showProgress = false, $multiByteUnicode = true)
	{
		$this->saveOptions($fileName, $convertQuotes, $showProgress, $multiByteUnicode);

		if (empty($fileName))
		{
			return '';
		}

		// Read the data from pdf file
		$pdfContent = @file_get_contents($this->filename, FILE_BINARY);
		$this->decodePDF($pdfContent);

		return $this->output();
	}

	/**
	 * Decode PDF content
	 *
	 * @param string $pdfContent    Binary PDF content
	 * @param int    $convertQuotes ENT_COMPAT (double-quotes), ENT_QUOTES (Both), ENT_NOQUOTES (None)
	 * @param bool   $showProgress  TRUE if you have problems with time-out
	 * @param bool   $multiByteUnicode
	 *
	 * @return string
	 */
	public function decodeContent($pdfContent, $convertQuotes = ENT_QUOTES, $showProgress = false, $multiByteUnicode = true)
	{
		$this->saveOptions('', $convertQuotes, $showProgress, $multiByteUnicode);
		$this->decodePDF($pdfContent);

		return $this->output();
	}

	/**
	 * Decode PDF
	 *
	 * @deprecated Use "decode" method instead
	 *
	 * @param string $pdfContent
	 *
	 * @return string
	 */
	public function decodePDF($pdfContent = '')
	{
		// Get all text data.
		$transformations = array();
		$texts           = array();

		// Get the list of all objects.
		preg_match_all("#obj[\n|\r](.*)endobj[\n|\r]#ismU", $pdfContent . "endobj\r", $objects);
		$objects = @$objects[1];

		// Select objects with streams.
		for ($i = 0; $i < count($objects); $i++)
		{
			$currentObject = $objects[$i];

			// Prevent time-out
			@set_time_limit(0);

			if ($this->showprogress)
			{
				flush();
				ob_flush();
			}

			// Check if an object includes data stream.
			if (preg_match("#stream[\n|\r](.*)endstream[\n|\r]#ismU", $currentObject . "endstream\r", $stream))
			{
				$stream = ltrim($stream[1]);

				// Check object parameters and look for text data.
				$options = $this->getObjectOptions($currentObject);

				if (!(empty($options["Length1"]) && empty($options["Type"]) && empty($options["Subtype"])))
				{
					continue;
				}

				// Hack, length doesnt always seem to be correct
				unset($options["Length"]);

				// So, we have text data. Decode it.
				$data = $this->getDecodedStream($stream, $options);

				if (strlen($data))
				{
					if (preg_match_all("#BT[\n|\r| ](.*)ET[\n|\r| ]#ismU", $data . "ET\r", $textContainers))
					{
						$textContainers = @$textContainers[1];
						$this->getDirtyTexts($texts, $textContainers);
					}
					else
					{
						$this->getCharTransformations($transformations, $data);
					}
				}
			}
		}
		// Analyze text blocks taking into account character transformations and return results.
		$this->decodedtext = $this->getTextUsingTransformations($texts, $transformations);

		// Analyze text blocks taking into account character transformations and return results.
		return $this->getTextUsingTransformations($texts, $transformations);
	}

	/**
	 * Decode ASCII Hex.
	 *
	 * @param string $input ASCII string.
	 *
	 * @return  string
	 */
	private function decodeAsciiHex($input)
	{
		$output    = "";
		$isOdd     = true;
		$isComment = false;

		for ($i = 0, $codeHigh = -1; $i < strlen($input) && $input[$i] !== '>'; $i++)
		{
			$c = $input[$i];

			if ($isComment)
			{
				if ($c === '\r' || $c === '\n')
				{
					$isComment = false;
				}
				continue;
			}

			switch ($c)
			{
				case '\0':
				case '\t':
				case '\r':
				case '\f':
				case '\n':
				case ' ':
					break;
				case '%':
					$isComment = true;
					break;

				default:
					$code = hexdec($c);
					if ($code === 0 && $c != '0')
					{
						return "";
					}

					if ($isOdd)
					{
						$codeHigh = $code;
					}
					else
					{
						$output .= chr($codeHigh * 16 + $code);
					}

					$isOdd = !$isOdd;
					break;
			}
		}

		if ($input[$i] !== '>')
		{
			return "";
		}

		if ($isOdd)
		{
			$output .= chr($codeHigh * 16);
		}

		return $output;
	}

	/**
	 * Descode ASCII 85.
	 *
	 * @param string $input ASCII string.
	 *
	 * @return  string
	 */
	private function decodeAscii85($input)
	{
		$output = "";

		$isComment = false;
		$ords      = array();

		for ($i = 0, $state = 0; $i < strlen($input) && $input[$i] !== '~'; $i++)
		{
			$c = $input[$i];

			if ($isComment)
			{
				if ($c === '\r' || $c === '\n')
				{
					$isComment = false;
				}
				continue;
			}

			if ($c === '\0' || $c === '\t' || $c === '\r' || $c === '\f' || $c === '\n' || $c === ' ')
			{
				continue;
			}
			if ($c === '%')
			{
				$isComment = true;
				continue;
			}
			if ($c === 'z' && $state === 0)
			{
				$output .= str_repeat(chr(0), 4);
				continue;
			}
			if ($c < '!' || $c > 'u')
			{
				return "";
			}

			$code           = ord($input[$i]) & 0xff;
			$ords[$state++] = $code - ord('!');

			if ($state == 5)
			{
				$state = 0;
				for ($sum = 0, $j = 0; $j < 5; $j++)
					$sum = $sum * 85 + $ords[$j];
				for ($j = 3; $j >= 0; $j--)
					$output .= chr($sum >> ($j * 8));
			}
		}
		if ($state === 1)
		{
			return "";
		}
		elseif ($state > 1)
		{
			for ($i = 0, $sum = 0; $i < $state; $i++)
				$sum += ($ords[$i] + ($i == $state - 1)) * pow(85, 4 - $i);
			for ($i = 0; $i < $state - 1; $i++)
			{
				try
				{
					if (false == ($o = chr($sum >> ((3 - $i) * 8))))
					{
						throw new \Exception('Error');
					}
					$output .= $o;
				}
				catch (\Exception $e)
				{ /*Dont do anything*/
				}
			}
		}

		return $output;
	}

	/**
	 * Decode Flate
	 *
	 * @param $data
	 *
	 * @return  string
	 */
	private function decodeFlate($data)
	{
		return @gzuncompress($data);
	}

	/**
	 * Get Object Options
	 *
	 * @param $object
	 *
	 * @return  array
	 */
	private function getObjectOptions($object)
	{
		$options = array();

		if (preg_match("#<<(.*)>>#ismU", $object, $options))
		{
			$options = explode("/", $options[1]);

			@array_shift($options);

			$o = array();

			for ($j = 0; $j < @count($options); $j++)
			{
				$options[$j] = preg_replace("#\s+#", " ", trim($options[$j]));

				if (strpos($options[$j], " ") !== false)
				{
					$parts        = explode(" ", $options[$j]);
					$o[$parts[0]] = $parts[1];
				}
				else
				{
					$o[$options[$j]] = true;
				}
			}

			$options = $o;

			unset($o);
		}

		return $options;
	}

	/**
	 * Get Decode Stream.
	 *
	 * @param $stream
	 * @param $options
	 *
	 * @return  string
	 */
	private function getDecodedStream($stream, $options)
	{
		$data = "";

		if (empty($options["Filter"]))
		{
			$data = $stream;
		}
		else
		{
			$length  = !empty($options["Length"]) ? $options["Length"] : strlen($stream);
			$_stream = substr($stream, 0, $length);

			foreach ($options as $key => $value)
			{
				if ($key === "ASCIIHexDecode")
				{
					$_stream = $this->decodeAsciiHex($_stream);
				}
				elseif ($key === "ASCII85Decode")
				{
					$_stream = $this->decodeAscii85($_stream);
				}
				elseif ($key === "FlateDecode")
				{
					$_stream = $this->decodeFlate($_stream);
				}
				elseif ($key === "Crypt")
				{ // TO DO
				}
			}
			$data = $_stream;
		}

		return $data;
	}

	/**
	 * Get Dirty Texts
	 *
	 * @param array $texts
	 * @param array $textContainers
	 *
	 * @return  void
	 */
	private function getDirtyTexts(&$texts, $textContainers)
	{
		for ($j = 0; $j < count($textContainers); $j++)
		{
			if (preg_match_all("#\[(.*)\]\s*TJ[\n|\r| ]#ismU", $textContainers[$j], $parts))
			{
				$texts = array_merge($texts, array(@implode('', $parts[1])));
			}
			elseif (preg_match_all("#T[d|w|m|f]\s*(\(.*\))\s*Tj[\n|\r| ]#ismU", $textContainers[$j], $parts))
			{
				$texts = array_merge($texts, array(@implode('', $parts[1])));
			}
			elseif (preg_match_all("#T[d|w|m|f]\s*(\[.*\])\s*Tj[\n|\r| ]#ismU", $textContainers[$j], $parts))
			{
				$texts = array_merge($texts, array(@implode('', $parts[1])));
			}
		}
	}

	/**
	 * Get Char Transformations
	 *
	 * @param $transformations
	 * @param $stream
	 *
	 * @return  void
	 */
	private function getCharTransformations(&$transformations, $stream)
	{
		preg_match_all("#([0-9]+)\s+beginbfchar(.*)endbfchar#ismU", $stream, $chars, PREG_SET_ORDER);
		preg_match_all("#([0-9]+)\s+beginbfrange(.*)endbfrange#ismU", $stream, $ranges, PREG_SET_ORDER);

		for ($j = 0; $j < count($chars); $j++)
		{
			$count   = $chars[$j][1];
			$current = explode("\n", trim($chars[$j][2]));

			for ($k = 0; $k < $count && $k < count($current); $k++)
			{
				if (preg_match("#<([0-9a-f]{2,4})>\s+<([0-9a-f]{4,512})>#is", trim($current[$k]), $map))
				{
					$transformations[str_pad($map[1], 4, "0")] = $map[2];
				}
			}
		}
		for ($j = 0; $j < count($ranges); $j++)
		{
			$count   = $ranges[$j][1];
			$current = explode("\n", trim($ranges[$j][2]));

			for ($k = 0; $k < $count && $k < count($current); $k++)
			{
				if (preg_match("#<([0-9a-f]{4})>\s+<([0-9a-f]{4})>\s+<([0-9a-f]{4})>#is", trim($current[$k]), $map))
				{
					$from  = hexdec($map[1]);
					$to    = hexdec($map[2]);
					$_from = hexdec($map[3]);

					for ($m = $from, $n = 0; $m <= $to; $m++, $n++)
					{
						$transformations[sprintf("%04X", $m)] = sprintf("%04X", $_from + $n);
					}
				}
				elseif (preg_match("#<([0-9a-f]{4})>\s+<([0-9a-f]{4})>\s+\[(.*)\]#ismU", trim($current[$k]), $map))
				{
					$from  = hexdec($map[1]);
					$to    = hexdec($map[2]);
					$parts = preg_split("#\s+#", trim($map[3]));

					for ($m = $from, $n = 0; $m <= $to && $n < count($parts); $m++, $n++)
					{
						$transformations[sprintf("%04X", $m)] = sprintf("%04X", hexdec($parts[$n]));
					}
				}
			}
		}
	}

	/**
	 * Get Text Using Transformations
	 *
	 * @param $texts
	 * @param $transformations
	 *
	 * @return  string
	 */
	private function getTextUsingTransformations($texts, $transformations)
	{
		$document = "";

		for ($i = 0; $i < count($texts); $i++)
		{
			$isHex   = false;
			$isPlain = false;

			$hex   = "";
			$plain = "";

			for ($j = 0; $j < strlen($texts[$i]); $j++)
			{
				$c = $texts[$i][$j];
				switch ($c)
				{
					case "<":
						$hex     = "";
						$isHex   = true;
						$isPlain = false;
						break;
					case ">":
						$hexs = str_split($hex, $this->multibyte); // 2 or 4 (UTF8 or ISO)
						for ($k = 0; $k < count($hexs); $k++)
						{

							$chex = str_pad($hexs[$k], 4, "0"); // Add tailing zero
							if (isset($transformations[$chex]))
							{
								$chex = $transformations[$chex];
							}
							$document .= html_entity_decode("&#x" . $chex . ";");
						}
						$isHex = false;
						break;
					case "(":
						$plain   = "";
						$isPlain = true;
						$isHex   = false;
						break;
					case ")":
						$document .= $plain;
						$isPlain = false;
						break;
					case "\\":
						$c2 = $texts[$i][$j + 1];
						if (in_array($c2, array("\\", "(", ")")))
						{
							$plain .= $c2;
						}
						elseif ($c2 === "n")
						{
							$plain .= '\n';
						}
						elseif ($c2 === "r")
						{
							$plain .= '\r';
						}
						elseif ($c2 === "t")
						{
							$plain .= '\t';
						}
						elseif ($c2 === "b")
						{
							$plain .= '\b';
						}
						elseif ($c2 === "f")
						{
							$plain .= '\f';
						}
						elseif ($c2 >= '0' && $c2 <= '9')
						{
							$oct = preg_replace("#[^0-9]#", "", substr($texts[$i], $j + 1, 3));
							$j += strlen($oct) - 1;
							$plain .= html_entity_decode("&#" . octdec($oct) . ";", $this->convertquotes);
						}
						$j++;
						break;

					default:
						if ($isHex)
						{
							$hex .= $c;
						}
						elseif ($isPlain)
						{
							$plain .= $c;
						}
						break;
				}
			}

			$document .= "\n";
		}

		return $document;
	}
}
