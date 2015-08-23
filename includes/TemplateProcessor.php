<?php
namespace Templates;
// TemplateProcessor processes the template code (logic class)
class TemplateProcessor {
	public  $code, 						// actual template code
			$lines, 					// array of lines
			$lineNr, 					// current line nr in processing proces
			$conditionLevel, 			// current condition level; 0 = no condition level, gets increased on each condition
			$whileLevel, 				// current while level, similar to conditionLevel, but for 'while' only
			$waitTillClosingCondition, 	// used to jump over condition blocks; if > 0 and < conditionLevel, skip lines
			$output,					// array of lines after processing is completed
			$vars = array(),			// variables to use while processing the template(s)
			$html,						// html text
			$blocks = array(),			// array (name-raw code)
			$parent,					// parent template name, used with the "extends" keyword
			$placeholders = array(),	// list of placeholder names, to quickly find a placeholder
			$closeBlockLevel,			// level at which the block keyword was opened
			$enterElse = false,			// true = enter else after next closing condition
			$highestLineNr = -1;		// furthes linenr ever reached, used to determine whether the current line is bein process the first time or not

	/**
	* constructor
	* $code = code to process
	* $vars = variables to load before processing
	*/
	function __construct($code, $vars = array()) {
		$this->vars = $vars;
		$this->code = preg_replace('/<<\s*(.*?)\s*>>/', '<<$1>>', $code);
		$this->lines = explode("\n", $this->code);
		$this->waitTillClosingCondition = -1;
		$this->conditionLevel = 0;
		$this->whileLevel = -1;
		$this->output = array();
		$this->closeBlockLevel = -1;
	}

	public function run() {
		$this->processLines($this->vars);

		if($this->parent != null) {
			$this->html = $this->passBlocks();
		}
		else {
			$this->html = preg_replace('/<<(.*?)>>/', '', implode('', $this->output));
		}

	}

	private function passBlocks() {
		$tpl = new Template($this->parent);
		$tpl->addBlocks($this->blocks);
		$tpl->run();
		return $tpl->getHTML();
	}

	/**
	* start processing
	*/
	private function processLines($vars = array()) {
		// load variables into the local scope
		foreach ($vars as $key => $value) {
			${$key} = $value;
		}
		$addToBlock = null;
		for($this->lineNr = 0; $this->lineNr < count($this->lines); $this->lineNr++) {
			if($this->lineNr > $this->highestLineNr) $this->highestLineNr = $this->lineNr;

			$this->line = trim($this->lines[$this->lineNr]); // remove spaces
			$this->lines[$this->lineNr] = $this->line;
			$skip = false; // if skip = true: this line will not be added to the output

			// get all tags
			preg_match_all('/<<(.*?)>>/', $this->line, $tags, PREG_OFFSET_CAPTURE);
			foreach ($tags[1] as $tag) {
				$tagText = trim($tag[0]); // trim tag

				// strip first characters only once ==> speed & power
				$first2 = substr($tagText, 0, 2);
				$first3 = substr($tagText, 0, 3);
				$first4 = substr($tagText, 0, 4);
				$first5 = substr($tagText, 0, 5);
				// closing-condition <<}>>
				if($tagText{0} == '}') {
					$name = trim(trim(trim($tagText, '}'), '{'));

					$this->conditionLevel--;
					if($this->conditionLevel < $this->closeBlockLevel) {
						$this->addToBlock = null;
						$this->closeBlockLevel = -1;
					}
					else
					if($this->conditionLevel < $this->whileLevel) {

						while($this->lineNr > 0 && !preg_match('/<<\s*(while|for)(.*?)>>/', $this->lines[$this->lineNr]))
							$this->lineNr--;
						$this->lineNr--;
					}
					else
					if($name == 'else') {
						$this->conditionLevel++;
						if($this->enterElse == true) {
							$this->waitTillClosingCondition = $this->conditionLevel; // condition matches -> jump into
							$this->enterElse = false;
						}
						else {
							$this->waitTillClosingCondition = $this->conditionLevel-1; // jump over
						}
					}
					else {
						$skip = true; // skip line (not required)
						continue; // skip further processing
					}
				}

				// DO NO ADD "else" here!!! needed for the while statement

				if($this->lineNr == 0 && substr($tagText, 0, 7) == 'extends') {
					$skip = true;
					$this->parent = trim(substr($tagText, 7));
				}
				else
				// if it's an 'if' statement and condition didn't match, wait for closing condition
				if($this->waitTillClosingCondition < $this->conditionLevel && $this->conditionLevel > 0) {
					$skip = true;
					continue;
				} 
				else
				if(substr($tagText, 0, 11) == 'placeholder') {
					$name = trim(substr($tagText, 11));
					if(isset($this->blocks[$name])) {
						$this->line = implode($this->blocks[$name], "\n");
					} 
					else $kip = true;
				}
				else
				if($first5 === 'block') {
					$this->conditionLevel++;
					$this->waitTillClosingCondition = $this->conditionLevel; // "jump into" the block
					$this->closeBlockLevel = $this->conditionLevel;
					$name = trim(substr($tagText, 5)); // remove spaces
					$name = rtrim($name, '{'); // remove curly bracket
					$name = rtrim($name); // remove spaces again
					$addToBlock = $name;
					$this->blocks[$addToBlock] = array();
					$skip = true;
				}
				// while-iteration <<while(con=dition) {>>
				else if($first5 === 'while') {
					$this->conditionLevel++;
					$condition = ltrim(substr($tagText, 5)); // remove 'while' & remove left spaces
					$condition = rtrim($condition, '{');
					$condition = rtrim($condition);
					if($this->conditionMatches($condition, get_defined_vars())) { // if condition matches, jump into while
						$this->waitTillClosingCondition = $this->conditionLevel;
						$this->whileLevel = $this->conditionLevel;
					}
					else { // no match ==> wait till closing curly bracket '}'
						$this->waitTillClosingCondition = $this->conditionLevel-1;
						$this->whileLevel = -1;
						if($this->highestLineNr <= $this->lineNr)
							$this->enterElse = true;
					}

				}
				
				else if($first3 === 'for') { // for-iteration TODO
					$this->conditionLevel++;
					$forCode = ltrim(substr($tagText, 3)); // remove 'for' & remove left spaces
					$forCode = rtrim($forCode, '{');
					$forCode = rtrim($forCode);
					$arr = explode(';', $forCode);
					if(count($arr) == 3) {
						list($first, $condition, $step) = $arr;
						// execute first
						if($this->highestLineNr <= $this->lineNr) {
							list($var, $val) = explode('=', $first);
							$var = trim($var);
							if($var{0} == '$') $var = substr($var, 1);
							$val = $this->convertCodeToValue($val);
							//global ${$var};
							${$var} = $val;
							$this->vars{$var} = $val;
							$vars{$var} = $val;
						}

						if($this->conditionMatches($condition, get_defined_vars())) {
							$this->waitTillClosingCondition = $this->conditionLevel;
							$this->whileLevel = $this->conditionLevel;
						}
						else {
							$this->waitTillClosingCondition = $this->conditionLevel-1;
							$this->whileLevel = -1;
							if($this->highestLineNr <= $this->lineNr)
								$this->enterElse = true;
						}
						eval($step.';');
					}
				}
				else
				// if-condition <<if(con=dition) {>>
				if($first2 === 'if') {
					$this->conditionLevel++;
					$condition = ltrim(substr($tagText, 2)); // remove 'if'
					$condition = rtrim($condition, '{');
					$condition = rtrim($condition);
					if($this->conditionMatches($condition, get_defined_vars())) {
						$this->waitTillClosingCondition = $this->conditionLevel; // condition matches -> jump into
					}
					else {
						$this->waitTillClosingCondition = $this->conditionLevel-1; // jump over
						$this->enterElse = true;
					}

				}
				// dump a variable <<dump var>>
				else if($first5  === 'dump ') {
					$var = trim(substr($tagText, 5)); // remove 'dump '
					ob_start(); // capture output buffer
					var_dump(${$var}); // dump into the buffer
					$result = ob_get_clean(); // get buffer contents
					$this->line = preg_replace('/<<\s*dump '.$var.'\s*>>/', $result, $this->line);
					//$this->line = str_replace('<<dump '.$var.'>>', $result, $this->line); // replace
				}
				// set variable to value <<set $var=val>>
				else if($first4  === 'set ') {
					$from4 = trim(substr($tagText, 4)); // remove 'set'

					list($var, $val) = explode('=', $from4);
					$var = trim($var);
					if($var{0} == '$') $var = substr($var, 1);
					$val = $this->convertCodeToValue($val);
					//global ${$var};
					${$var} = $val;
					$this->vars{$var} = $val;
					$vars{$var} = $val;
				}
				// echo variable <<echo variable/formula>>
				else if($first5 == 'echo ') {
					// print
					$noEcho = substr($tagText, 5);
					$this->line = str_replace('<<'.$tagText.'>>', eval('return '.$this->replacePipesWithFunctions($noEcho, $vars).';'), $this->line); // replace tag with eval'd code
				}
				else
				if($tagText{0} == '$') {
					// print
					$this->line = str_replace('<<'.$tagText.'>>', eval('return '.$tagText.';'), $this->line);
				}
			}

			$this->line = preg_replace('/<<(.*?)>>/', '', $this->line);
			if(strlen($this->line) == 0) continue;

			// check for skipping aswel, because there might me no tags in this line, which results in the skipping of the previous foreach
			if($skip === false && !($this->waitTillClosingCondition < $this->conditionLevel && $this->conditionLevel > 0)
			) {
				// if addtoblock is set, add to blocks array, else add to output
				if($addToBlock != null)
					$this->blocks[$addToBlock][] = $this->line;
				else
					$this->output[] = $this->line;
			}
		}
	}

	private function convertCodeToValue($code) {
		$val = trim($code);
		if(is_numeric($val)) { // if it's a number, store as a number instead of a string
			$val = (int)$val;
		}
		else {
			// convert from hex/bin/dec to dec
			$lastChar = substr($val, -1);
			$untillLastChar = substr($val, 0, -1);
			if($lastChar == 'd' && is_numeric($untillLastChar))
				$val = (int)$untillLastChar;
			else if($lastChar == 'h' && ctype_xdigit($untillLastChar))
				$val = hexdec($untillLastChar);	
			else if($lastChar == 'b' && preg_match('~^[01]+$~', $untillLastChar))
				$val = bindec($untillLastChar);
			else
				$val = eval('return '.$val.';'); // use eval to allow php code to be executed
		}
		return $val;
	}

	/**
	* getVar
	* $key = variable name to get
	* $vars = key-value array with the variables
	*/
	private function getVar($key, $vars) {
		return $vars[$key];
	}

	/**
	* conditionMatches checks if a text condition matches
	* $condition = the condition; examples are:
	*	- "(condition) {"
	*	- "condition{"
	*	- " condition {"
	*	==> brackets are optional, on both sides of the statement
	*	==> spaces are optional, will be ignored anywhere
	* $vars = key-value array with the variables
	*/
	private function conditionMatches($condition, $vars) {
		// load vars locally
		foreach ($vars as $key => $value) {
			${$key} = $value;
		}
		if(substr($condition, 0, 1) === '{') $condition = rtrim(substr($condition, 0, -1)); // remove '{', and spaces after it
		// normal brackets
		if(substr($condition, 0, 1) === '(') {
			$condition = substr($condition, 1, 0); // remove '(' in the beginning	
			if(substr($condition, -1) === ')') $condition = substr($condition, 0, -1); // remove ')' at the end
		}
		
		$condition = trim($condition); // remove any spaces before & after

		$condition = $this->replacePipesWithFunctions($condition, $vars);


		//$condition = preg_replace('/\$(.*?)\|length/', '\$$1', $condition);
		return eval('return '.$condition.';');
	}

	/**
	* search for pipes in condition and edit the condition in such a way that the function is added.
	* eg: $test|length will be replaced by count($test) or strlen($test)
	*/
	public function replacePipesWithFunctions($condition, $vars) {
		// load vars locally
		foreach ($vars as $key => $value) {
			${$key} = $value;
		}

		// new line to <br />
		preg_match_all('/\$(.*?)\|(reverse|rev)/', $condition, $matches);
		foreach ($matches[1] as $key => $match) {
			$var = ${$match};
			$replace = null;
			if(is_array($var)) $replace = 'array_reverse($'.$match.')';
			else $replace = 'strrev($'.$match.')';

			$condition = preg_replace('/\$'.$match.'\|(reverse|rev)/', $replace, $condition);
		}


		// length, size & count
		preg_match_all('/\$(.*?)\|(length|size|count)/', $condition, $matches);
		foreach ($matches[1] as $key => $match) {
			$var = ${$match};
			$replace = null;
			if(is_array($var)) $replace = 'count($'.$match.')';
			else $replace = 'strlen($'.$match.')';
			$condition = preg_replace('/\$'.$match.'\|(length|size|count)/', $replace, $condition);
		}

		// last
		preg_match_all('/\$(.*?)\|(last)/', $condition, $matches);
		foreach ($matches[1] as $key => $match) {
			$var = ${$match};
			$replace = null;
			if(is_array($var)) $replace = 'end($'.$match.')';
			else if(is_string($var) || is_numeric($var)) $replace = 'substr($'.$match.', -1)';
			
			$condition = preg_replace('/\$'.$match.'\|(last)/', $replace, $condition);
		}

		// first
		preg_match_all('/\$(.*?)\|(first)/', $condition, $matches);
		foreach ($matches[1] as $key => $match) {
			$var = ${$match};
			$replace = null;
			if(is_array($var)) $replace = 'reset($'.$match.')';
			else if(is_string($var) || is_numeric($var)) $replace = '$'.$match.'[0]';
			
			$condition = preg_replace('/\$'.$match.'\|(first)/', $replace, $condition);
		}



		// date
		preg_match_all('/\$(.*?)\|(date|time|datetime)\s?\((.*?)\)/', $condition, $matches);
		foreach ($matches[1] as $key => $match) {
			$format = $matches[3][$key];
			$format = trim($format);
			$format = trim($format, '\'');
			$format = trim($format, '"');
			$var = ${$match};
			$condition = preg_replace('/\$'.$match.'\|(date|time|datetime)\s?\((.*?)\)/', 'date("'.addslashes($format).'", $'.$match.')', $condition);
		}


		// round
		preg_match_all('/\$(.*?)\|round\s?\((.*?)\)/', $condition, $matches);
		foreach ($matches[1] as $key => $match) {
			$dec = $matches[2][$key];
			$dec = trim($dec);
			$dec = trim($dec, '\'');
			$dec = trim($dec, '"');

			$condition = preg_replace('/\$'.$match.'\|round\s?\((.*?)\)/', 'round($'.$match.', '.$dec.')', $condition);
		}
		// round without parameters
		$condition = preg_replace('/\$(.*?)\|round/', 'round($$1)', $condition);


		// trim
		preg_match_all('/\$(.*?)\|trim\s?\((.*?)\)/', $condition, $matches);
		foreach ($matches[1] as $key => $match) {
			$dec = $matches[2][$key];
			$dec = trim($dec);
			$dec = trim($dec, '\'');
			$dec = trim($dec, '"');

			$condition = preg_replace('/\$'.$match.'\|trim\s?\((.*?)\)/', 'trim($'.$match.', '.$dec.')', $condition);
		}
		// trim without parameters
		$condition = preg_replace('/\$(.*?)\|trim/', 'trim($$1)', $condition);


		// number_format
		preg_match_all('/\$(.*?)\|number\_format\s?\((.*?)\)/', $condition, $matches);
		foreach ($matches[1] as $key => $match) {
			$parameters = $matches[2][$key];

			$condition = preg_replace('/\$'.$match.'\|number\_format\s?\((.*?)\)/', 'number_format($'.$match.', '.$parameters.')', $condition);
		}
		// number_format without parameters
		$condition = preg_replace('/\$(.*?)\|number\_format/', 'number_format($$1)', $condition);
		

		// uppercase
		$condition = preg_replace('/\$(.*?)\|(upper|uppercase|capitalize)/', 'strtoupper($$1)', $condition);
		// lowercase
		$condition = preg_replace('/\$(.*?)\|(lower|lowercase)/', 'strtolower($$1)', $condition);

		// new line to <br />
		$condition = preg_replace('/\$(.*?)\|nl2br/', 'nl2br($$1)', $condition);

		/* math */
		$condition = preg_replace('/\$(.*?)\|(abs)/', 'abs($$1)', $condition);
		$condition = preg_replace('/\$(.*?)\|(floor)/', 'floor($$1)', $condition);
		$condition = preg_replace('/\$(.*?)\|(ceil)/', 'ceil($$1)', $condition);
		$condition = preg_replace('/\$(.*?)\|(max)/', 'max($$1)', $condition);
		$condition = preg_replace('/\$(.*?)\|(min)/', 'min($$1)', $condition);

		return $condition;
	}

	/**
	* getHTML of the processed code
	*/
	public function getHTML() {
		return $this->html;
	}

	public function injectVariable($name, $value) {
		$this->vars[$name] = $value;
	}

	public function append($tpl) {
		if($tpl instanceof TemplateProcessor) {
			$this->output = array_merge($this->output, $tpl->output);
			$this->code .= $tpl->code;
			$this->lines = array_merge($this->lines, $tpl->lines);
			$this->vars = array_merge($this->vars, $tpl->vars);
			if($this->html != null && $tpl->html != null) $this->html .= $tpl->html;
		}

		return $this;
	}
}