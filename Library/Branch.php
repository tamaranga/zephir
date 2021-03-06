<?php

class Branch
{
	protected $_parentBranch;

	protected $_level = -1;

	protected $_relatedStatement;

	const TYPE_ROOT              = 0;

	const TYPE_CONDITIONAL_TRUE  = 1;

	const TYPE_CONDITIONAL_FALSE = 2;

	const TYPE_LOOP_INFINITE     = 3;

	const TYPE_LOOP_CONDITIONAL  = 4;

	const TYPE_SWITCH            = 5;

	const TYPE_EXTERNAL          = 6;

	const TYPE_UNKNOWN           = 7;

	public function setParentBranch(Branch $parentBranch)
	{
		$this->_parentBranch = $parentBranch;
	}

	public function getParentBranch()
	{
		return $this->_parentBranch;
	}

	public function setType($type)
	{
		$this->_type = $type;
	}

	public function getType()
	{
		return $this->_type;
	}

	public function setUnrecheable($unrecheable)
	{
		$this->_unrecheable = $unrecheable;
	}

	public function isUnrecheable()
	{
		return $this->_unrecheable;
	}

	public function setLevel($level)
	{
		$this->_level = $level;
	}

	public function getLevel()
	{
		return $this->_level;
	}

	public function setUniqueId($uniqueId)
	{
		$this->_uniqueId = $uniqueId;
	}

	public function getUniqueId()
	{
		return $this->_uniqueId;
	}

	public function setRelatedStatement($relatedStatement)
	{
		$this->_relatedStatement = $relatedStatement;
	}

	public function getRelatedStatement()
	{
		return $this->_relatedStatement;
	}

}