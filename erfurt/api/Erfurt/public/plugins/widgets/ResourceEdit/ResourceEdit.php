<?php

/**
  * Erfurt resource edit widget
  *
  * @package plugin
  * @subpackage widget
  * @author Norman Heino <norman@feedface.de>
  * @version $Id$
  */
class ResourceEdit extends Erfurt_Plugin_Widget {
	
	public function __construct($elementName = null, $values = null, $config = array()) {
		parent::__construct($elementName, 
			                $values, 
							// config
							$config
		);
		
		$this->config['class'] = 'ResourceEditContainer';
		$this->scripts[] = $this->widgetBaseUrl . 'ResourceEdit/resource_edit.js';
		$this->styles[] = $this->widgetBaseUrl . 'ResourceEdit/resource_edit.css';
	}
	
	// public function __toString() {
	// 	$ret = '<input type="hidden" id="model-' . $this->id . '" value="' . $this->config['modelUri'] . '" />' . PHP_EOL;
	// 	
	// 	$ret .= parent::__toString();
	// 	
	// 	return $ret;
	// }
	
	public function getSingleValueHtml($resource = '', $num = 1) {
		if ($resource instanceof Resource) {
			$value = $resource->getLocalName();
			$uri = $resource->getURI();
		// TODO: why check for Literal here???
		} elseif ($resource instanceof Literal) {
			$value = $resource->getLabel();
		} else {
			$value = $resource;
		}

		$name = $this->elementName . '[' . $num . ']';
		
		if (isset($this->config['nameMod'])) {
			$nameMod .= '[' . $this->config['nameMod'] . ']';
		} else {
			$nameMod = '';
		}
		
		$ret  = '<div>' . PHP_EOL;
		// local name input
		$ret .= '<input type="text" name="' . $name . $nameMod . '[uri]" class="ResourceEditValue text" value="' . $value . 
				'" id="value-' . $this->id . $num . '" />' . PHP_EOL;
		$ret .= '<img class="delete button" id="img-' . $this->id . $num . '" src="' . $this->publicUri . 'images/delete.gif" alt="del" />' . PHP_EOL;
				// onkeyup="$(\'uri-' . $this->id . $num . '\').value = this.value" 
		// autocompleter div
		$ret .= '<div id="autocomplete-choices-' . $this->id . $num . '" class="autosuggest" style="display:none"></div>' . PHP_EOL;
		// autocompleter script
		$ret .= '<script type="text/javascript">getAutocompleter(\'' . $this->id . $num . '\')</script>' . PHP_EOL;
		// uri input (filled by autocompleter hook) 
		// $ret .= '<input type="hidden" name="' . $name . $nameMod . '[value]" class="ResourceEditUri" value="' . $uri . 
		// 		'" id="uri-' . $this->id . $num . '" />' . PHP_EOL;
		$ret .= '</div>' . PHP_EOL;
		
		return $ret;
	}
}

?>