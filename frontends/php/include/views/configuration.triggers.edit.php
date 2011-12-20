<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once('include/views/js/configuration.triggers.edit.js.php');

$triggersWidget = new CWidget();
if (!empty($this->data['parent_discoveryid'])) {
	$triggersWidget->addPageHeader(_('CONFIGURATION OF TRIGGER PROTOTYPES'));
}
else {
	$triggersWidget->addPageHeader(_('CONFIGURATION OF TRIGGERS'));
}

// create form
$triggersForm = new CForm();
$triggersForm->setName('triggersForm');
$triggersForm->addVar('form', $this->data['form']);
$triggersForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
$triggersForm->addVar('expression', $this->data['expression']);
$triggersForm->addVar('input_method', $this->data['input_method']);
$triggersForm->addVar('toggle_input_method', '');
$triggersForm->addVar('remove_expression', '');
if (!empty($this->data['triggerid'])) {
	$triggersForm->addVar('triggerid', $this->data['triggerid']);
}

// create form list
$triggersFormList = new CFormList('triggersFormList');
if (!empty($this->data['templates'])) {
	$triggersFormList->addRow(_('Templates'), $this->data['templates']);
}
$triggersFormList->addRow(_('Name'), new CTextBox('description', $this->data['description'], ZBX_TEXTBOX_STANDARD_SIZE, $this->data['limited']));

// append expression to form list
$expressionTextBox = new CTextArea(
	$this->data['expression_field_name'],
	$this->data['expression_field_value'],
	ZBX_TEXTAREA_STANDARD_ROWS,
	ZBX_TEXTAREA_STANDARD_WIDTH,
	$this->data['expression_field_readonly']
);
$addExpressionButton = new CButton(
	'insert',
	$this->data['input_method'] == IM_TREE ? _('Edit') : _('Add'),
	'return PopUp(\'popup_trexpr.php?dstfrm='.$triggersForm->getName().
		'&dstfld1='.$this->data['expression_field_name'].'&srctbl=expression'.url_param('parent_discoveryid').
		'&srcfld1=expression&expression=\' + escape('.$this->data['expression_field_params'].'), 800, 230);',
	'formlist'
);
if ($this->data['limited'] == 'yes') {
	$addExpressionButton->setAttribute('disabled', 'disabled');
}
$expressionTitle = array(_('Expression'));
$expressionRow = array($expressionTextBox, $addExpressionButton);
if (!empty($this->data['expression_macro_button'])) {
	array_push($expressionRow, $this->data['expression_macro_button']);
}
if ($this->data['input_method'] == IM_TREE) {
	array_push($expressionRow, BR());
	if (empty($this->data['outline'])) {
		// add button
		$addExpressionButton = new CSubmit('add_expression', _('Add'), null, 'formlist');
		if ($this->data['limited'] == 'yes') {
			$addExpressionButton->setAttribute('disabled', 'disabled');
		}
		array_push($expressionRow, $addExpressionButton);
	}
	else {
		// add button
		$addExpressionButton = new CSubmit('and_expression', _('AND'), null, 'formlist');
		if ($this->data['limited'] == 'yes') {
			$addExpressionButton->setAttribute('disabled', 'disabled');
		}
		array_push($expressionRow, $addExpressionButton);

		// or button
		$orExpressionButton = new CSubmit('or_expression', _('OR'), null, 'formlist');
		if ($this->data['limited'] == 'yes') {
			$orExpressionButton->setAttribute('disabled', 'disabled');
		}
		array_push($expressionRow, $orExpressionButton);

		// replace button
		$replaceExpressionButton = new CSubmit('replace_expression', _('Replace'), null, 'formlist');
		if ($this->data['limited'] == 'yes') {
			$replaceExpressionButton->setAttribute('disabled', 'disabled');
		}
		array_push($expressionRow, $replaceExpressionButton);
	}
}
elseif ($this->data['input_method'] != IM_FORCED) {
	$inputMethodToggle = new CSpan(_('Expression constructor'), 'link');
	$inputMethodToggle->setAttribute('onclick', 'javascript: '.
		'document.getElementById(\'toggle_input_method\').value=1;'.
		'document.getElementById(\'input_method\').value='.($this->data['input_method'] == IM_TREE ? IM_ESTABLISHED : IM_TREE).';'.
		'document.forms[\''.$triggersForm->getName().'\'].submit();'
	);
	$expressionRow[] = array(BR(), $inputMethodToggle);
}
$triggersFormList->addRow($expressionTitle, $expressionRow);

// append expression table to form list
if ($this->data['input_method'] == IM_TREE) {
	$expressionTable = new CTable(null, 'formElementTable');
	$expressionTable->setAttribute('style', 'min-width:500px;');
	$expressionTable->setAttribute('id', 'exp_list');
	$expressionTable->setOddRowClass('even_row');
	$expressionTable->setEvenRowClass('even_row');
	$expressionTable->setHeader(array(
		$this->data['limited'] == 'yes' ? null : _('Target'),
		_('Expression'),
		empty($this->data['parent_discoveryid']) ? _('Error') : null,
		$this->data['limited'] == 'yes' ? null : _('Action')
	));

	$allowedTesting = true;
	if (!empty($this->data['eHTMLTree'])) {
		foreach ($this->data['eHTMLTree'] as $i => $e) {
			if ($this->data['limited'] != 'yes') {
				$deleteUrl = new CSpan(_('Delete'), 'link');
				$deleteUrl->setAttribute('onclick', 'javascript:'.
					' if (Confirm(\''._('Delete expression?').'\')) {'.
						' delete_expression(\''.$e['id'] .'\');'.
						' document.forms[\''.$triggersForm->getName().'\'].submit();'.
					' }'
				);
				$triggerCheckbox = new CCheckbox('expr_target_single', $i == 0 ? 'yes' : 'no', 'check_target(this);', $e['id']);
			}
			else {
				$triggerCheckbox = null;
			}

			if (empty($this->data['parent_discoveryid'])) {
				if (!isset($e['expression']['levelErrors'])) {
					$errorImg = new CImg('images/general/ok_icon.png', 'expression_no_errors');
					$errorImg->setHint(_('No errors found.'));
				}
				else {
					$allowedTesting = false;
					$errorImg = new CImg('images/general/error_icon.png', 'expression_errors');
					$errorTexts = array();
					if (is_array($e['expression']['levelErrors'])) {
						foreach ($e['expression']['levelErrors'] as $expVal => $errTxt) {
							if (count($errorTexts) > 0) {
								array_push($errorTexts, BR());
							}
							array_push($errorTexts, $expVal, ':', $errTxt);
						}
					}
					$errorImg->setHint($errorTexts, '', 'left');
				}
				$errorColumn = new CCol($errorImg, 'center');
			}
			else {
				$errorColumn = null;
			}

			// templated trigger
			if ($this->data['limited'] == 'yes') {
				// make all links inside inactive
				$listSize = count($e['list']);
				for ($i = 0; $i < $listSize; $i++) {
					if (gettype($e['list'][$i]) == 'object' && get_class($e['list'][$i]) == 'CSpan' && $e['list'][$i]->getAttribute('class') == 'link') {
						$e['list'][$i]->removeAttribute('class');
						$e['list'][$i]->setAttribute('onclick', '');
					}
				}
			}

			$row = new CRow(array($triggerCheckbox, $e['list'], $errorColumn, isset($deleteUrl) ? $deleteUrl : null));
			$expressionTable->addRow($row);
		}
	}
	else {
		$allowedTesting = false;
		$this->data['outline'] = '';
	}

	$testButton = new CButton('test_expression', _('Test'),
		'openWinCentered(\'tr_testexpr.php?expression=\' + encodeURIComponent(this.form.elements[\'expression\'].value),'.
		'\'ExpressionTest\', 850, 400, \'titlebar=no, resizable=yes, scrollbars=yes\'); return false;',
		'link_menu'
	);
	if (!$allowedTesting) {
		$testButton->setAttribute('disabled', 'disabled');
	}
	if (empty($this->data['outline'])) {
		$testButton->setAttribute('disabled', 'disabled');
	}

	$wrapOutline = new CSpan(array($this->data['outline']));
	$wrapOutline->addStyle('white-space: pre;');
	$triggersFormList->addRow(SPACE, array(
		$wrapOutline,
		BR(),
		BR(),
		new CDiv(array($expressionTable, $testButton), 'objectgroup inlineblock border_dotted ui-corner-all')
	));

	$inputMethodToggle = new CSpan(_('Close expression constructor'), 'link');
	$inputMethodToggle->setAttribute('onclick', 'javascript: '.
		'document.getElementById(\'toggle_input_method\').value=1;'.
		'document.getElementById(\'input_method\').value='.IM_ESTABLISHED.';'.
		'document.forms[\''.$triggersForm->getName().'\'].submit();'
	);
	$triggersFormList->addRow(SPACE, array($inputMethodToggle, BR()));
}

$triggersFormList->addRow(_('Multiple PROBLEM events generation'), new CCheckBox('type', ($this->data['type'] == 0) ? 'yes' : 'no', null, 1));
$triggersFormList->addRow(_('Comments'), new CTextArea('comments', $this->data['comments']));
$triggersFormList->addRow(_('URL'), new CTextBox('url', $this->data['url'], ZBX_TEXTBOX_STANDARD_SIZE));

// append severity to form list
$labelNotClassified = new CLabel(_($this->data['config']['severity_name_'.TRIGGER_SEVERITY_NOT_CLASSIFIED]), 'severity_'.TRIGGER_SEVERITY_NOT_CLASSIFIED, 'severity_label_'.TRIGGER_SEVERITY_NOT_CLASSIFIED);
$labelNotClassified->addAction('onmouseover', 'mouseOverSeverity('.TRIGGER_SEVERITY_NOT_CLASSIFIED.');');
$labelNotClassified->addAction('onmouseout', 'mouseOutSeverity('.TRIGGER_SEVERITY_NOT_CLASSIFIED.');');
$labelNotClassified->addAction('onclick', 'focusSeverity('.TRIGGER_SEVERITY_NOT_CLASSIFIED.');');

$labelInformation = new CLabel(_($this->data['config']['severity_name_'.TRIGGER_SEVERITY_INFORMATION]), 'severity_'.TRIGGER_SEVERITY_INFORMATION, 'severity_label_'.TRIGGER_SEVERITY_INFORMATION);
$labelInformation->addAction('onmouseover', 'mouseOverSeverity('.TRIGGER_SEVERITY_INFORMATION.');');
$labelInformation->addAction('onmouseout', 'mouseOutSeverity('.TRIGGER_SEVERITY_INFORMATION.');');
$labelInformation->addAction('onclick', 'focusSeverity('.TRIGGER_SEVERITY_INFORMATION.');');

$labelWarning = new CLabel(_($this->data['config']['severity_name_'.TRIGGER_SEVERITY_WARNING]), 'severity_'.TRIGGER_SEVERITY_WARNING, 'severity_label_'.TRIGGER_SEVERITY_WARNING);
$labelWarning->addAction('onmouseover', 'mouseOverSeverity('.TRIGGER_SEVERITY_WARNING.');');
$labelWarning->addAction('onmouseout', 'mouseOutSeverity('.TRIGGER_SEVERITY_WARNING.');');
$labelWarning->addAction('onclick', 'focusSeverity('.TRIGGER_SEVERITY_WARNING.');');

$labelAverage = new CLabel(_($this->data['config']['severity_name_'.TRIGGER_SEVERITY_AVERAGE]), 'severity_'.TRIGGER_SEVERITY_AVERAGE, 'severity_label_'.TRIGGER_SEVERITY_AVERAGE);
$labelAverage->addAction('onmouseover', 'mouseOverSeverity('.TRIGGER_SEVERITY_AVERAGE.');');
$labelAverage->addAction('onmouseout', 'mouseOutSeverity('.TRIGGER_SEVERITY_AVERAGE.');');
$labelAverage->addAction('onclick', 'focusSeverity('.TRIGGER_SEVERITY_AVERAGE.');');

$labelHigh = new CLabel(_($this->data['config']['severity_name_'.TRIGGER_SEVERITY_HIGH]), 'severity_'.TRIGGER_SEVERITY_HIGH, 'severity_label_'.TRIGGER_SEVERITY_HIGH);
$labelHigh->addAction('onmouseover', 'mouseOverSeverity('.TRIGGER_SEVERITY_HIGH.');');
$labelHigh->addAction('onmouseout', 'mouseOutSeverity('.TRIGGER_SEVERITY_HIGH.');');
$labelHigh->addAction('onclick', 'focusSeverity('.TRIGGER_SEVERITY_HIGH.');');

$labelDisaster = new CLabel(_($this->data['config']['severity_name_'.TRIGGER_SEVERITY_DISASTER]), 'severity_'.TRIGGER_SEVERITY_DISASTER, 'severity_label_'.TRIGGER_SEVERITY_DISASTER);
$labelDisaster->addAction('onmouseover', 'mouseOverSeverity('.TRIGGER_SEVERITY_DISASTER.');');
$labelDisaster->addAction('onmouseout', 'mouseOutSeverity('.TRIGGER_SEVERITY_DISASTER.');');
$labelDisaster->addAction('onclick', 'focusSeverity('.TRIGGER_SEVERITY_DISASTER.');');

$triggersFormList->addRow(_('Severity'), new CDiv(
	array(
		new CRadioButton('priority', TRIGGER_SEVERITY_NOT_CLASSIFIED, null, 'severity_'.TRIGGER_SEVERITY_NOT_CLASSIFIED, $this->data['priority'] == TRIGGER_SEVERITY_NOT_CLASSIFIED),
		$labelNotClassified,
		new CRadioButton('priority', TRIGGER_SEVERITY_INFORMATION, null, 'severity_'.TRIGGER_SEVERITY_INFORMATION, $this->data['priority'] == TRIGGER_SEVERITY_INFORMATION),
		$labelInformation,
		new CRadioButton('priority', TRIGGER_SEVERITY_WARNING, null, 'severity_'.TRIGGER_SEVERITY_WARNING, $this->data['priority'] == TRIGGER_SEVERITY_WARNING),
		$labelWarning,
		new CRadioButton('priority', TRIGGER_SEVERITY_AVERAGE, null, 'severity_'.TRIGGER_SEVERITY_AVERAGE, $this->data['priority'] == TRIGGER_SEVERITY_AVERAGE),
		$labelAverage,
		new CRadioButton('priority', TRIGGER_SEVERITY_HIGH, null, 'severity_'.TRIGGER_SEVERITY_HIGH, $this->data['priority'] == TRIGGER_SEVERITY_HIGH),
		$labelHigh,
		new CRadioButton('priority', TRIGGER_SEVERITY_DISASTER, null, 'severity_'.TRIGGER_SEVERITY_DISASTER, $this->data['priority'] == TRIGGER_SEVERITY_DISASTER),
		$labelDisaster
	),
	'jqueryinputset'
));

// append status to form list
if (empty($this->data['triggerid']) && empty($this->data['form_refresh'])) {
	$status = 'yes';
}
else {
	$status = $this->data['status'] == 0 ? 'yes' : 'no';
}
$triggersFormList->addRow(_('Enabled'), new CCheckBox('status', $status, null, 1));

// append tabs to form
$triggersTab = new CTabView(array('remember' => true));
if (!$this->data['form_refresh']) {
	$triggersTab->setSelected(0);
}
$triggersTab->addTab('triggersTab', _('Trigger'), $triggersFormList);

/*
 * Dependencies tab
 */
if (empty($this->data['parent_discoveryid'])) {
	$dependencesFormList = new CFormList('dependencesFormList');
	$dependencesTable = new CTable(_('No dependencies defined.'), 'formElementTable');
	$dependencesTable->setAttribute('style', 'min-width:500px;');
	$dependencesTable->setAttribute('id', 'dependenciesTable');
	$dependencesTable->setHeader(array(
		_('Name'),
		_('Action')
	));

	foreach ($this->data['dependence'] as $dependence) {
		$triggersForm->addVar('dependencies[]', $dependence['triggerid'], 'dependencies_'.$dependence['triggerid']);

		$row = new CRow(array(
			$dependence['description'],
			new CButton('remove', _('Remove'), 'javascript: removeDependence(\''.$dependence['triggerid'].'\');', 'link_menu')
		));
		$row->setAttribute('id', 'dependence_'.$dependence['triggerid']);
		$dependencesTable->addRow($row);
	}
	$dependencesFormList->addRow(
		_('Dependencies'),
		new CDiv(
			array(
				$dependencesTable,
				new CButton('bnt1', _('Add'), 'return PopUp(\'popup.php?srctbl=triggers&srcfld1=triggerid&reference=deptrigger&multiselect=1\', 1000, 700);', 'link_menu')
			),
			'objectgroup inlineblock border_dotted ui-corner-all'
		)
	);
	$triggersTab->addTab('dependencesTab', _('Dependencies'), $dependencesFormList);
}

// append tabs to form
$triggersForm->addItem($triggersTab);

// append buttons to form
$buttons = array();
if (!empty($this->data['triggerid'])) {
	$buttons[] = new CSubmit('clone', _('Clone'));

	$deleteButton = new CButtonDelete(_('Delete trigger?'), url_param('form').url_param('groupid').url_param('hostid').url_param('triggerid').url_param('parent_discoveryid'));
	if ($this->data['limited']) {
		$deleteButton->setAttribute('disabled', 'disabled');
	}
	$buttons [] = $deleteButton;
}
$buttons[] = new CButtonCancel(url_param('groupid').url_param('hostid').url_param('parent_discoveryid'));
$triggersForm->addItem(makeFormFooter(
	array(new CSubmit('save', _('Save'))),
	array($buttons)
));

$triggersWidget->addItem($triggersForm);
return $triggersWidget;
?>
