<?php
require_once "Link.php";

//under construction
class Erfurt_Sparql_Query2_Abstraction_ClassNode 
{
	protected $shownproperties = array();
	protected $filters = array();
	
	protected $type;
	protected $classVar;
	
	protected $outgoinglinks;
	protected $query;
	
	public function __construct(Erfurt_Sparql_Query2_IriRef $type, $member_predicate = EF_RDF_TYPE, Erfurt_Sparql_Query2 $query, $varName = null, $withChilds = true){
		
		$this->query = $query;
		
		if($member_predicate==EF_RDF_TYPE){
			$type = new Erfurt_Sparql_Query2_Abstraction_RDFSClass($type, $withChilds);
			$member_predicate = new Erfurt_Sparql_Query2_A();
		} else 
			$type = new Erfurt_Sparql_Query2_Abstraction_NoClass($type, $member_predicate);
			if(is_string($member_predicate))
				$member_predicate = new Erfurt_Sparql_Query2_IriRef($member_predicate);
		
		
		$this->type = $type;
		if($varName == null)
			$this->classVar = new Erfurt_Sparql_Query2_Var($type->getIri());
		else
			$this->classVar = new Erfurt_Sparql_Query2_Var($varName);
			
		
		if(!($member_predicate instanceof Erfurt_Sparql_Query2_Verb)){
			throw new RuntimeException("Argument 2 passed to Erfurt_Sparql_Query2_Abstraction_ClassNode::__construct must be an instance of Erfurt_Sparql_Query2_IriRef or string instance of ".typeHelper($predicate)." given");
		}
		
		$subclasses = $type->getSubclasses();
		if(!empty($subclasses)){
			$typeVar = new Erfurt_Sparql_Query2_Var($type->getIri());
			$typePart= new Erfurt_Sparql_Query2_Triple($this->classVar, $member_predicate, $typeVar);
			$this->query->getWhere()->addElement($typePart);
			$or = new Erfurt_Sparql_Query2_ConditionalOrExpression(); 
			foreach($subclasses as $subclass){
				$or->addElement(new Erfurt_Sparql_Query2_sameTerm($typeVar, $subclass));
			}
			$filter = new Erfurt_Sparql_Query2_Filter($or);
			$this->query->getWhere()->addElement($filter);
		} else {
			$typePart= new Erfurt_Sparql_Query2_Triple($this->classVar, $member_predicate, $type->getIri());
			$this->query->getWhere()->addElement($typePart);
		}
	}
	
	public function __clone() {
	} 
	
	
	public function addShownProperty($predicate, $name = null, $inverse = false){
		if(is_string($predicate)){
			$predicate = new Erfurt_Sparql_Query2_IriRef($predicate);
		}
		if(!($predicate instanceof Erfurt_Sparql_Query2_IriRef)){
			throw new RuntimeException("Argument 1 passed to Erfurt_Sparql_Query2_Abstraction_ClassNode::addFilter must be an instance of Erfurt_Sparql_Query2_IriRef instance of ".typeHelper($predicate)." given");
		}
		
		$optionalpart = new Erfurt_Sparql_Query2_OptionalGraphPattern();

		if($name == null)
			$var = new Erfurt_Sparql_Query2_Var($predicate);
		else 
			$var = new Erfurt_Sparql_Query2_Var($name);
		
		if(!$inverse)
			$triple = new Erfurt_Sparql_Query2_Triple($this->classVar, $predicate, $var);
		else 
			$triple = new Erfurt_Sparql_Query2_Triple($var, $predicate, $this->classVar);
			
		$optionalpart->addElement($triple);
		$this->query->getWhere()->addElement($optionalpart);
		
		$this->query->addProjectionVar($var);
		
		$this->shownproperties[] = array("optional" => $optionalpart, "var" => $var);
		return $this; //for chaining
	}
	
	public function addLink($predicate, Erfurt_Sparql_Query2_Abstraction_ClassNode $target){
		if(is_string($predicate)){
			$predicate = new Erfurt_Sparql_Query2_IriRef($predicate);
		}
		if(!($predicate instanceof Erfurt_Sparql_Query2_IriRef)){
			throw new RuntimeException("Argument 1 passed to Erfurt_Sparql_Query2_Abstraction_ClassNode::addFilter must be an instance of Erfurt_Sparql_Query2_IriRef instance of ".typeHelper($predicate)." given");
		}		
		
		$this->outgoinglinks[] = new Erfurt_Sparql_Query2_Abstraction_Link($predicate, $target);
		$this->query->getWhere()->addElement(new Erfurt_Sparql_Query2_Triple($this->classVar, $predicate, new Erfurt_Sparql_Query2_Var($target->getClass()->getIri())));
		return $this; //for chaining
	}
	
	public function addFilter($predicate, $type, $value){
		if(is_string($predicate)){
			$predicate = new Erfurt_Sparql_Query2_IriRef($predicate);
		}
		if(!($predicate instanceof Erfurt_Sparql_Query2_IriRef)){
			throw new RuntimeException("Argument 1 passed to Erfurt_Sparql_Query2_Abstraction_ClassNode::addFilter must be an instance of Erfurt_Sparql_Query2_IriRef instance of ".typeHelper($predicate)." given");
		}
		switch($type){
			case "contains":
				$propVar = new Erfurt_Sparql_Query2_Var($predicate);
				$filteringTriple = new Erfurt_Sparql_Query2_Triple($this->getClassVar(), $predicate, $propVar);
				$filterExp = new Erfurt_Sparql_Query2_Filter(new Erfurt_Sparql_Query2_Regex($propVar, new Erfurt_Sparql_Query2_RDFLiteral($value)));
				$this->query->getWhere()->addElement($filteringTriple);
				$this->query->getWhere()->addElement($filterExp);
				$this->filters[] = array($filteringTriple, $filterExp);
			break;
			case "equals":
				$filteringTriple = new Erfurt_Sparql_Query2_Triple($this->getClassVar(), $predicate, new Erfurt_Sparql_Query2_RDFLiteral($value));
				$this->query->getWhere()->addElement($filteringTriple);
				$this->filters[] = $filteringTriple;
			break;
		}
		
		return $this;
	}
	
	public function clearShownProperties(){
		foreach($this->shownproperties as $pair){
			$this->query->removeProjectionVar($pair["var"]);
			$pair["optional"]->remove();
		}
		return $this;
	}
	
	public function clearFilter(){
		foreach($this->filters as $filter){
			if(is_array($filter)){
				foreach($filter as $tripleOrFilter){
					$tripleOrFilter->remove();
				}
			} else $filter->remove();
		}
		return $this->type;
	}
	
	public function clearAll(){
		$this->clearShownProperties();
		$this->clearFilter();
		
		return $this;
	}
	
	public function getClass(){
		return $this->type;
	}
	
	public function getClassVar(){
		return $this->classVar;
	}
}
?>