<?php
// Template class processes the filename/code (data class)
namespace Templates;
include 'includes/TemplateProcessor.php';

class Template {
	/**
	* constructor
	* $code (required) = template code or location (*.tpl, *.htplml, *.html)
	* $vars (optional) = key-value array containing the variables used while processing this template
	*/
	private $pTpl;
	public function __construct($code, $vars = array()) {
		try {
			if(file_exists($code) && !is_dir($code)) {
				$code = file_get_contents($code);
			}
			else if(file_exists($code.'.tpl') && !is_dir($code.'.tpl')) {
				$code = file_get_contents($code.'.tpl');
			}
			else if(file_exists($code.'.htplml') && !is_dir($code.'.htplml')) {
				$code = file_get_contents($code.'.htplml');
			}
			else if(file_exists($code.'.html') && !is_dir($code.'.html')) {
				$code = file_get_contents($code.'.tpl');
			}
		} catch (Exception $e) {
		}
		$this->pTpl = new TemplateProcessor($code, $vars);
	}

	public function run() {
		$this->pTpl->run();
	}

	public function addVariable($name, $value) {
		$this->injectVariable($name, $value);
	}

	public function injectVariable($name, $value) {
		$this->pTpl->injectVariable($name, $value);
	}

	public function addBlocks($blocks) {
		$this->pTpl->blocks = array_merge($this->pTpl->blocks, $blocks);
	}

	/**
	* append the given template to the current template
	*/
	public function append($tpl) {
		if($tpl instanceof Template)
			$this->pTpl->append($tpl->pTpl);

		return $this;
	}

	/**
	* append the current template to the given template
	*/
	public function appendTo($tpl) {
		if($tpl instanceof Template)
			$tpl->pTpl->append($this->pTpl);

		return $this;
	}

	/**
	* prepend the given template to the current template
	*/
	public function prepend($tpl) {
		if($tpl instanceof Template) {
			$tpl->pTpl->append($this->pTpl);
			$this->pTpl = $tpl->pTpl;
		}

		return $this;
	}

	/**
	* getHTML - as the name says
	*/
	public function getHTML() {
		return $this->pTpl->getHTML();
	}
}