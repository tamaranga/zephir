<?php

/*
 +--------------------------------------------------------------------------+
 | Zephir Language                                                          |
 +--------------------------------------------------------------------------+
 | Copyright (c) 2013-2014 Zephir Team and contributors                     |
 +--------------------------------------------------------------------------+
 | This source file is subject the MIT license, that is bundled with        |
 | this package in the file LICENSE, and is available through the           |
 | world-wide-web at the following url:                                     |
 | http://zephir-lang.com/license.html                                      |
 |                                                                          |
 | If you did not receive a copy of the MIT license and are unable          |
 | to obtain it through the world-wide-web, please send a note to           |
 | license@zephir-lang.com so we can mail you a copy immediately.           |
 +--------------------------------------------------------------------------+
*/

/**
 * NativeArrayAccess
 *
 * Resolves expressions that read array indexes
 */
class NativeArrayAccess
{
	protected $_expecting = true;

	protected $_readOnly = false;

	protected $_expectingVariable;

	/**
	 * Sets if the variable must be resolved into a direct variable symbol
	 * create a temporary value or ignore the return value
	 *
	 * @param boolean $expecting
	 * @param Variable $expectingVariable
	 */
	public function setExpectReturn($expecting, Variable $expectingVariable=null)
	{
		$this->_expecting = $expecting;
		$this->_expectingVariable = $expectingVariable;
	}

	/**
	 * Sets if the result of the evaluated expression is read only
	 *
	 * @param boolean $readOnly
	 */
	public function setReadOnly($readOnly)
	{
		$this->_readOnly = $readOnly;
	}

	/**
	 * @param array $expression
	 * @param Variable $variableVariable
	 * @param CompilationContext $compilationContext
	 */
	protected function _accessStringOffset($expression, Variable $variableVariable, CompilationContext $compilationContext)
	{

		if ($this->_expecting) {
			if ($this->_expectingVariable) {
				$symbolVariable = $this->_expectingVariable;
				if ($symbolVariable->getType() != 'char') {
					$symbolVariable = $compilationContext->symbolTable->getTempNonTrackedVariable('char', $compilationContext);
				}
			} else {
				$symbolVariable = $compilationContext->symbolTable->getTempNonTrackedVariable('char', $compilationContext);
			}
		}

		/**
		 * Right part of expression is the index
		 */
		$expr = new Expression($expression['right']);
		$exprIndex = $expr->compile($compilationContext);

		$codePrinter = $compilationContext->codePrinter;

		switch ($exprIndex->getType()) {

			case 'int':
			case 'uint':
			case 'long':
				$compilationContext->headersManager->add('kernel/operators');
				$codePrinter->output($symbolVariable->getName() . ' = ZEPHIR_STRING_OFFSET(' . $variableVariable->getName() . ', ' . $exprIndex->getCode() . ');');
				break;

			case 'variable':
				$variableIndex = $compilationContext->symbolTable->getVariableForRead($exprIndex->getCode(), $compilationContext, $expression);
				switch ($variableIndex->getType()) {

					case 'int':
					case 'uint':
					case 'long':
						$codePrinter->output($symbolVariable->getName() . ' = ZEPHIR_STRING_OFFSET(' . $variableVariable->getName() . ', ' . $variableIndex->getName() . ');');
						break;

					default:
						throw new CompilerException("Cannot use index type " . $variableIndex->getType() . " as offset", $expression['right']);
				}
				break;

			default:
				throw new CompilerException("Cannot use index type " . $exprIndex->getType() . " as offset", $expression['right']);
		}

		return new CompiledExpression('variable', $symbolVariable->getName(), $expression);
	}

	/**
	 * @param array $expression
	 * @param Variable $variableVariable
	 * @param CompilationContext $compilationContext
	 */
	protected function _accessDimensionArray($expression, Variable $variableVariable, CompilationContext $compilationContext)
	{
		$arrayAccess = $expression;

		if ($variableVariable->hasAnyDynamicType('unknown')) {
			throw new CompilerException("Cannot use non-initialized variable as an array", $arrayAccess['left']);
		}

		/**
		 * Trying to use a non-object dynamic variable as object
		 */
		if ($variableVariable->hasDifferentDynamicType(array('undefined', 'array', 'null'))) {
			$compilationContext->logger->warning('Possible attempt to access array-index on a non-array dynamic variable', 'non-array-access', $arrayAccess['left']);
		}

		$codePrinter = $compilationContext->codePrinter;

		/**
		 * Resolves the symbol that expects the value
		 */
		$readOnly = false;
		if ($this->_readOnly) {
			if ($this->_expecting) {
				if ($this->_expectingVariable) {

					$symbolVariable = $this->_expectingVariable;

					/**
					 * If a variable is assigned once in the method, we try to promote it
					 * to a read only variable
					 */
					if ($symbolVariable->getName() != 'return_value') {
						$numberMutations = $compilationContext->symbolTable->getExpectedMutations($symbolVariable->getName());
						if ($numberMutations == 1) {
							if ($symbolVariable->getNumberMutations() == $numberMutations) {
								$symbolVariable->setMemoryTracked(false);
								$readOnly = true;
							}
						}
					}

					/**
					 * Variable is not read-only or it wasn't promoted
					 */
					if (!$readOnly) {
						if ($symbolVariable->getName() != 'return_value') {
							$symbolVariable->observeVariant($compilationContext);
							$this->_readOnly = false;
						} else {
							$symbolVariable = $compilationContext->symbolTable->getTempNonTrackedUninitializedVariable('variable', $compilationContext, $expression);
						}
					}

				} else {
					$symbolVariable = $compilationContext->symbolTable->getTempNonTrackedUninitializedVariable('variable', $compilationContext, $expression);
				}
			} else {
				$symbolVariable = $compilationContext->symbolTable->getTempNonTrackedUninitializedVariable('variable', $compilationContext, $expression);
			}

		} else {
			if ($this->_expecting) {
				if ($this->_expectingVariable) {

					$symbolVariable = $this->_expectingVariable;

					/**
					 * If a variable is assigned once in the method, we try to promote it
					 * to a read only variable
					 */
					if ($symbolVariable->getName() != 'return_value') {
						$numberMutations = $compilationContext->symbolTable->getExpectedMutations($symbolVariable->getName());
						if ($numberMutations == 1) {
							if ($symbolVariable->getNumberMutations() == $numberMutations) {
								$symbolVariable->setMemoryTracked(false);
								$readOnly = true;
							}
						}
					}

					/**
					 * Variable is not read-only or it wasn't promoted
					 */
					if (!$readOnly) {
						if ($symbolVariable->getName() != 'return_value') {
							$symbolVariable->observeVariant($compilationContext);
							$this->_readOnly = false;
						} else {
							$symbolVariable = $compilationContext->symbolTable->getTempVariableForObserve('variable', $compilationContext, $expression);
						}
					}

				} else {
					$symbolVariable = $compilationContext->symbolTable->getTempVariableForObserve('variable', $compilationContext, $expression);
				}
			} else {
				$symbolVariable = $compilationContext->symbolTable->getTempVariableForObserve('variable', $compilationContext, $expression);
			}
		}

		/**
		 * Variable that receives property accesses must be polimorphic
		 */
		if ($symbolVariable->getType() != 'variable') {
			throw new CompilerException("Cannot use variable: " . $symbolVariable->getType() . " to assign array index", $expression);
		}

		/**
		 * At this point, we don't know the type fetched from the index
		 */
		$symbolVariable->setDynamicTypes('undefined');

		if ($this->_readOnly || $readOnly) {
			$flags = 'PH_NOISY | PH_READONLY';
		} else {
			$flags = 'PH_NOISY';
		}

		/**
		 * Right part of expression is the index
		 */
		$expr = new Expression($arrayAccess['right']);
		$exprIndex = $expr->compile($compilationContext);

		switch ($exprIndex->getType()) {
			case 'int':
			case 'uint':
			case 'long':
				$compilationContext->headersManager->add('kernel/array');
				$codePrinter->output('zephir_array_fetch_long(&' . $symbolVariable->getName() . ', ' . $variableVariable->getName() . ', ' . $exprIndex->getCode() . ', ' . $flags . ' TSRMLS_CC);');
				break;
			case 'string':
				$compilationContext->headersManager->add('kernel/array');
				$codePrinter->output('zephir_array_fetch_string(&' . $symbolVariable->getName() . ', ' . $variableVariable->getName() . ', SL("' . $exprIndex->getCode() . '"), ' . $flags . ' TSRMLS_CC);');
				break;
			case 'variable':
				$variableIndex = $compilationContext->symbolTable->getVariableForRead($exprIndex->getCode(), $compilationContext, $expression);
				switch ($variableIndex->getType()) {
					case 'int':
					case 'uint':
					case 'long':
						$compilationContext->headersManager->add('kernel/array');
						$codePrinter->output('zephir_array_fetch_long(&' . $symbolVariable->getName() . ', ' . $variableVariable->getName() . ', ' . $variableIndex->getName() . ', ' . $flags . ' TSRMLS_CC);');
						break;
					case 'string':
					case 'variable':
						$compilationContext->headersManager->add('kernel/array');
						$codePrinter->output('zephir_array_fetch(&' . $symbolVariable->getName() . ', ' . $variableVariable->getName() . ', ' . $variableIndex->getName() . ', ' . $flags . ' TSRMLS_CC);');
						break;
					default:
						throw new CompilerException("Variable type: " . $variableIndex->getType() . " cannot be used as array index without cast", $arrayAccess['right']);
				}
				break;
			default:
				throw new CompilerException("Cannot use expression: " . $exprIndex->getType() . " as array index without cast", $arrayAccess['right']);
		}

		return new CompiledExpression('variable', $symbolVariable->getRealName(), $expression);
	}

	/**
	 * Compiles foo[x] = {expr}
	 *
	 * @param array $expression
	 * @param CompilationContext $compilationContext
	 * @return \CompiledExpression
	 */
	public function compile($expression, CompilationContext $compilationContext)
	{

		/**
		 * Resolve the left part of the expression
		 */
		$expr = new Expression($expression['left']);
		$expr->setReadOnly(true);
		$exprVariable = $expr->compile($compilationContext);

		/**
		 * Only dynamic variables can be used as arrays
		 */
		switch ($exprVariable->getType()) {
			case 'variable':
				$variableVariable = $compilationContext->symbolTable->getVariableForRead($exprVariable->getCode(), $compilationContext, $expression);
				switch ($variableVariable->getType()) {
					case 'variable':
					case 'array':
					case 'string':
						break;
					default:
						throw new CompilerException("Variable type: " . $variableVariable->getType() . " cannot be used as array", $expression['left']);
				}
				break;
			default:
				throw new CompilerException("Cannot use expression: " . $exprVariable->getType() . " as an array", $expression['left']);
		}

		/**
		 * Resolve the dimension according to variable's type
		 */
		switch ($variableVariable->getType()) {
			case 'variable':
				return $this->_accessDimensionArray($expression, $variableVariable, $compilationContext);
			case 'array':
				break;
			case 'string':
				return $this->_accessStringOffset($expression, $variableVariable, $compilationContext);
		}

	}

}
