<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__) . '/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../traits/FilterTrait.php';

/**
 * @backup widget
 *
 * @on-before disableDebugMode
 * @on-after enableDebugMode
 */
class testGraphWidget extends CWebTest {

	use FilterTrait;

	/*
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboardid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name';

	/**
	 * Open dashboard and add/edit graph widget.
	 *
	 * @param string $name		name of graphic widget to be opened
	 */
	private function openGraphWidgetConfiguration($name = null) {
		$dashboard = CDashboardElement::find()->one()->edit();

		// Open existed widget by widget name.
		if ($name) {
			$widget = $dashboard->getWidget($name);
			$this->assertEquals(true, $widget->isEditable());
			$form = $widget->edit();
		}
		// Add new graph widget.
		else {
			$overlay = $dashboard->addWidget();
			$form = $overlay->asForm();
			$form->fill(['Type' => 'Graph']);
			$form->waitUntilReloaded();
		}

		return $form;
	}

	/**
	 * Save dashboard and check added/updated graph widget.
	 *
	 * @param string $name		name of graphic widget to be checked
	 */
	private function saveGraphWidget($name) {
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget($name);
		$widget->getContent()->query('class:svg-graph')->waitUntilVisible();
		$dashboard->save();
		$message = CMessageElement::find()->waitUntilPresent()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());
	}

	/**
	 * Check validation of graph widget tabs fields.
	 */
	private function validate($data, $tab) {
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=103');
		$form = $this->openGraphWidgetConfiguration(CTestArrayHelper::get($data, 'Widget name'));

		$this->fillDatasets(CTestArrayHelper::get($data, 'Data set'));

		switch ($tab) {
			case 'Data set':
				// Remove data set.
				if (CTestArrayHelper::get($data, 'remove_data_set', false)) {
					$form->query('xpath://button[@class="remove-btn"]')->one()->click();
				}
				break;

			case 'Overrides':
				$form->selectTab($tab);
				$this->fillOverrides(CTestArrayHelper::get($data, 'Overrides'));

				// Remove all overide options.
				if (CTestArrayHelper::get($data, 'remove_override_options', false)) {
					$form->query('xpath://button[@class="subfilter-disable-btn"]')->all()->click();
				}

				break;

			default:
				$form->selectTab($tab);
				$form->fill($data[$tab]);
		}

		sleep(2);
		$form->submit();
		$form->parents('id:overlay_dialogue')->query('xpath:div[@class="overlay-dialogue-footer"]'.
				'//button[@class="dialogue-widget-save"]')->one()->waitUntilClickable();

		if (!is_array($data['error'])) {
			$data['error'] = [$data['error']];
		}
		// Check error message.
		$message = $form->getOverlayMessage();
		$this->assertTrue($message->isBad());
		$count = count($data['error']);
		$message->query('xpath:./div[@class="msg-details"]/ul/li['.$count.']')->waitUntilPresent();
		$this->assertEquals($count, $message->getLines()->count());

		foreach ($data['error'] as $error) {
			$this->assertTrue($message->hasLine($error));
		}

		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public static function getDatasetValidationData() {
		return [
			[
				[
					'remove_data_set' => true,
					'error' => 'Invalid parameter "Data set": cannot be empty.'
				]
			],
			// Base colour field validation.
			[
				[
					'Data set' => [
						[
							'fields' => [
								'Base colour' => ''
							]
						]
					],
					'error' => 'Invalid parameter "Data set/1/color": cannot be empty.'
				]
			],
			[
				[
					'Data set' => [
						[
							'fields' => [
								'Base colour' => '00000!'
							]
						]
					],
					'error' => 'Invalid parameter "Data set/1/color": a hexadecimal colour code (6 symbols) is expected.'
				]
			],
			[
				[
					'Data set' => [
						[
							'fields' => [
								'Base colour' => '00000'
							]
						]
					],
					'error' => 'Invalid parameter "Data set/1/color": a hexadecimal colour code (6 symbols) is expected.'
				]
			],
			// Time shift field validation.
			[
				[
					'Data set' => [
						[
							'fields' => [
								'Time shift' => 'abc',
								'Draw' => 'Points'
							]
						]
					],
					'error' => 'Invalid parameter "Data set/1/timeshift": a time unit is expected.'
				]
			],
			[
				[
					'Data set' => [
						[
							'fields' => [
								'Time shift' => '5.2'
							]
						]
					],
					'error' => 'Invalid parameter "Data set/1/timeshift": a time unit is expected.'
				]
			],
			[
				[
					'Data set' => [
						[
							'fields' => [
								'Time shift' => '10000d'
							]
						]
					],
					'error' => 'Invalid parameter "Data set/1/timeshift": value must be one of -788400000-788400000.'
				]
			],
			[
				[
					'Data set' => [
						[
							'fields' => [
								'Time shift' => '999999999999999999999999999'
							]
						]
					],
					'error' => 'Invalid parameter "Data set/1/timeshift": a number is too large.'
				]
			],
			// Validation of second data set.
			[
				[
					'Data set' => [
						[],
						[
							'fields' => [
								'Time shift' => '5'
							]
						]
					],
					'error' => 'Invalid parameter "Data set/2/hosts": cannot be empty.'
				]
			],
			[
				[
					'Data set' => [
						[],
						[
							'item' => 'test',
							'fields' => [
								'Time shift' => '5'
							]
						]
					],
					'error' => 'Invalid parameter "Data set/2/hosts": cannot be empty.'
				]
			],
			[
				[
					'Data set' => [
						[],
						[
							'host' => 'test'
						]
					],
					'error' => 'Invalid parameter "Data set/2/items": cannot be empty.'
				]
			],
			[
				[
					'Data set' => [
						[],
						[
							'host' => 'Zabbix*',
							'item' => 'Agent ping',
							'fields' => [
								'Base colour' => '00000'
							]
						]
					],
					'error' => 'Invalid parameter "Data set/2/color": a hexadecimal colour code (6 symbols) is expected.'
				]
			],
			[
				[
					'Data set' => [
						[],
						[
							'host' => '*',
							'item' => '*',
							'fields' => [
								'Time shift' => 'abc',
								'Draw' => 'Points'
							]
						]
					],
					'error' => 'Invalid parameter "Data set/2/timeshift": a time unit is expected.'
				]
			]
		];
	}

	/*
	 * Data provider for "Data set" tab validation on creating.
	 */
	public function getDatasetValidationCreateData() {
		$data = [];

		// Add host and item values for the first "Data set" in each case of the data provider.
		foreach ($this->getDatasetValidationData() as $item) {
			if (array_key_exists('Data set', $item[0])) {
				$item[0]['Data set'][0] = array_merge($item[0]['Data set'][0], [
					'host' => 'ЗАББИКС Сервер',
					'item' => 'Agent ping'
				]);
			}

			$data[] = $item;
		}

		return array_merge($data, [
			// Empty host and/or item field.
			[
				[
					'error' => 'Invalid parameter "Data set/1/hosts": cannot be empty.'
				]
			],
			[
				[
					'Data set' => [
						'item' => '*'
					],
					'error' => 'Invalid parameter "Data set/1/hosts": cannot be empty.'
				]
			],
			[
				[
					'Data set' => [
						'host' => '*'
					],
					'error' => 'Invalid parameter "Data set/1/items": cannot be empty.'
				]
			],
			// Space instead of host name.
			[
				[
					'Data set' => [
						'host' => ' ',
						'item' => '*'
					],
					'error' => 'Invalid parameter "Data set/1/hosts": cannot be empty.'
				]
			],
			// Space instead of item name.
			[
				[
					'Data set' => [
						'host' => '*',
						'item' => ' '
					],
					'error' => 'Invalid parameter "Data set/1/items": cannot be empty.'
				]
			]
		]);
	}

	/*
	 * Data provider for "Data set" tab validation on updating.
	 */
	public function getDatasetValidationUpdateData() {
		$data = [];

		// Add existing widget name for each case in data provider.
		foreach ($this->getDatasetValidationData() as $item) {
				$item[0]['Widget name'] = 'Test cases for update';

			$data[] = $item;
		}

		// Add additional validation cases.
		return array_merge($data, [
			[
				[
					'Widget name' => 'Test cases for update',
					'Data set' => [
						'host' => '',
						'item' => ''
					],
					'error' => 'Invalid parameter "Data set/1/hosts": cannot be empty.'
				]
			],
			[
				[
					'Widget name' => 'Test cases for update',
					'Data set' => [
						'host' => '',
						'item' => '*'
					],
					'error' => 'Invalid parameter "Data set/1/hosts": cannot be empty.'
				]
			],
			[
				[
					'Widget name' => 'Test cases for update',
					'Data set' => [
						'host' => '*',
						'item' => ''
					],
					'error' => 'Invalid parameter "Data set/1/items": cannot be empty.'
				]
			]
		]);
	}

	/**
	 * Check validation of "Data set" tab.
	 *
	 * @dataProvider getDatasetValidationCreateData
	 * @dataProvider getDatasetValidationUpdateData
	 */
	public function testGraphWidget_DatasetValidation($data) {
		$this->validate($data, 'Data set');
	}

	public static function getTimePeriodValidationData() {
		return [
			// Empty From/To fields.
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => '',
						'To' => ''
					],
					'error' => [
						'Invalid parameter "From": cannot be empty.',
						'Invalid parameter "To": cannot be empty.'
					]
				]
			],
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => '2019-07-31 15:53:07',
						'To' => ''
					],
					'error' => 'Invalid parameter "To": cannot be empty.'
				]
			],
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => '',
						'To' => '2019-07-31 15:53:07'
					],
					'error' => [
						'Invalid parameter "From": cannot be empty.',
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			// Date format validation (YYYY-MM-DD HH-MM-SS)
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => '1',
						'To' => '2019-07-31 15:53:07'
					],
					'error' => [
						'Invalid parameter "From": a time range is expected.',
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => '2019-07-31 15:53:07',
						'To' => 'abc'
					],
					'error' => 'Invalid parameter "To": a time range is expected.'
				]
			],
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => '5:53:06 2019-07-31',
						'To' => '2019-07-31 15:53:07'
					],
					'error' => [
						'Invalid parameter "From": a time range is expected.',
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => '2019-02-30 00:00:00',
						'To' => '2019-07-31 15:53:07'
					],
					'error' => [
						'Invalid parameter "From": a time range is expected.',
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => '2019-05-02 00:00:00',
						'To' => '2019-25-09 00:00:00'
					],
					'error' => 'Invalid parameter "To": a time range is expected.'
				]
			],
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => '2019-05-02 00:00:00',
						'To' => '2019.07.31 15:53:07'
					],
					'error' => 'Invalid parameter "To": a time range is expected.'
				]
			],
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => '2019-07-04 12:53:00',
						'To' => 'now-s'
					],
					'error' => 'Invalid parameter "To": a time range is expected.'
				]
			],
			// Time range validation
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => '2019-07-04 12:53:00',
						'To' => '2019-07-04 12:52:59'
					],
					'error' => 'Minimum time period to display is 1 minute.'
				]
			],
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => '2019-07-04 12:53:00',
						'To' => '2019-07-04 12:52:59'
					],
					'error' => 'Minimum time period to display is 1 minute.'
				]
			],
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => '2022-07-04 12:53:00',
						'To' => 'now'
					],
					'error' => 'Minimum time period to display is 1 minute.'
				]
			],
			[
				[
					'Time period' => [
						'Set custom time period' => true,
						'From' => 'now-58s',
						'To' => 'now'
					],
					'error' => 'Minimum time period to display is 1 minute.'
				]
			]
		];
	}

	public function getTimePeriodValidationCreateData() {
		$data = [];

		// Add host and item values for each case in data provider.
		foreach ($this->getTimePeriodValidationData() as $item) {
			$item[0]['Data set'] = [
				'host' => 'ЗАББИКС Сервер',
				'item' => 'Agent ping'
			];

			$data[] = $item;
		}

		return $data;
	}

	public function getTimePeriodValidationUpdateData() {
		$data = [];

		foreach ($this->getTimePeriodValidationData() as $item) {
				$item[0]['Widget name'] = 'Test cases for update';

			$data[] = $item;
		}

		return $data;
	}

	/**
	 * Check validation of "Time period" tab.
	 *
	 * @dataProvider getTimePeriodValidationCreateData
	 * @dataProvider getTimePeriodValidationUpdateData
	 */
	public function testGraphWidget_TimePeriodValidation($data) {
		$this->validate($data, 'Time period');
	}

	public static function getAxesValidationData() {
		return [
			// Left Y-axis validation. Set by default in first data set.
			[
				[
					'Axes' => [
						'id:lefty_min' => 'abc'
					],
					'error' => 'Invalid parameter "Min": a number is expected.'
				]
			],
			[
				[
					'Axes' => [
						'id:lefty_max' => 'abc'
					],
					'error' => 'Invalid parameter "Max": a number is expected.'
				]
			],
			[
				[
					'Axes' => [
						'id:lefty_min' => '10',
						'id:lefty_max' => '5'
					],
					'error' => 'Invalid parameter "Max": Y axis MAX value must be greater than Y axis MIN value.'
				]
			],
			[
				[
					'Axes' => [
						'id:lefty_min' => '-5',
						'id:lefty_max' => '-10'
					],
					'error' => 'Invalid parameter "Max": Y axis MAX value must be greater than Y axis MIN value.'
				]
			],
			// Change default Y-axis option on Right.
			[
				[
					'Data set' => [
						[
							'fields' => ['Y-axis' => 'Right']
						]
					],
					'Axes' => [
						'id:righty_min' => 'abc'
					],
					'error' => 'Invalid parameter "Min": a number is expected.'
				]
			],
			[
				[
					'Data set' => [
						[
							'fields' => ['Y-axis' => 'Right']
						]
					],
					'Axes' => [
						'id:righty_max' => 'abc'
					],
					'error' => 'Invalid parameter "Max": a number is expected.'
				]
			],
			[
				[
					'Data set' => [
						[
							'fields' => ['Y-axis' => 'Right']
						]
					],
					'Axes' => [
						'id:righty_min' => '10',
						'id:righty_max' => '5'
					],
					'error' => 'Invalid parameter "Max": Y axis MAX value must be greater than Y axis MIN value.'
				]
			],
			[
				[
					'Data set' => [
						[
							'fields' => ['Y-axis' => 'Right']
						]
					],
					'Axes' => [
						'id:righty_min' => '-5',
						'id:righty_max' => '-10'
					],
					'error' => 'Invalid parameter "Max": Y axis MAX value must be greater than Y axis MIN value.'
				]
			],
			// Both axes validation.
			[
				[
					'Data set' => [
						[
							'fields' => ['Y-axis' => 'Right']
						],
						[
							'host' => 'ЗАББИКС Сервер',
							'item' => 'Agent ping',
							'fields' => ['Y-axis' => 'Left']
						]
					],
					'Axes' => [
						'id:lefty_max' => 'abc',
						'id:righty_max' => 'abc'
					],
					'error' => [
						'Invalid parameter "Max": a number is expected.',
						'Invalid parameter "Max": a number is expected.'
					]
				]
			],
			[
				[
					'Data set' => [
						[
							'fields' => ['Y-axis' => 'Right']
						],
						[
							'host' => 'ЗАББИКС Сервер',
							'item' => 'Agent ping',
							'fields' => ['Y-axis' => 'Left']
						]
					],
					'Axes' => [
						'id:lefty_min' => '-5',
						'id:lefty_max' => '-10',
						'id:righty_min' => '10',
						'id:righty_max' => '5'
					],
					'error' => [
						'Invalid parameter "Max": Y axis MAX value must be greater than Y axis MIN value.',
						'Invalid parameter "Max": Y axis MAX value must be greater than Y axis MIN value.'
					]
				]
			],
			[
				[
					'Data set' => [
						[
							'fields' => ['Y-axis' => 'Right']
						],
						[
							'host' => 'ЗАББИКС Сервер',
							'item' => 'Agent ping',
							'fields' => ['Y-axis' => 'Left']
						]
					],
					'Axes' => [
						'id:lefty_min' => 'abc',
						'id:lefty_max' => 'def',
						'id:righty_min' => '!@#',
						'id:righty_max' => '('
					],
					'error' => [
						'Invalid parameter "Min": a number is expected.',
						'Invalid parameter "Min": a number is expected.',
						'Invalid parameter "Max": a number is expected.',
						'Invalid parameter "Max": a number is expected.'
					]
				]
			]
		];
	}

	/*
	 * Add host and item values in data provider.
	 */
	public function getAxesValidationCreateData() {
		$data = [];

		foreach ($this->getAxesValidationData() as $item) {
			if (array_key_exists('Data set', $item[0])) {
				$item[0]['Data set'][0] = array_merge($item[0]['Data set'][0], [
					'host' => 'ЗАББИКС Сервер',
					'item' => 'Agent ping'
				]);
			}
			else {
				$item[0]['Data set'] = [
					'host' => 'ЗАББИКС Сервер',
					'item' => 'Agent ping'
				];
			}

			$data[] = $item;
		}

		return $data;
	}

	public function getAxesValidationUpdateData() {
		$data = [];

		foreach ($this->getAxesValidationData() as $item) {
				$item[0]['Widget name'] = 'Test cases for simple update and deletion';

			$data[] = $item;
		}

		return $data;
	}

	/**
	 * Check "Axes" tab validation.
	 *
	 * @dataProvider getAxesValidationCreateData
	 * @dataProvider getAxesValidationUpdateData
	 */
	public function testGraphWidget_AxesValidation($data) {
		$this->validate($data, 'Axes');
	}

	public static function getOverridesValidationData() {
		return [
			// Base colour field validation.
			[
				[
					'Overrides' => [
						[
							'options' => [
								'Base colour'
							]
						]
					],
					'error' => 'Invalid parameter "Overrides/1/color": cannot be empty.'
				]
			],
			[
				[
					'Overrides' => [
						[
							'color' => '00000!',
							'options' => [
								'Base colour'
							]
						]
					],
					'error' => 'Invalid parameter "Overrides/1/color": a hexadecimal colour code (6 symbols) is expected.'
				]
			],
			[
				[
					'Overrides' => [
						[
							'color' => '00000',
							'options' => [
								'Base colour'
							]
						]
					],
					'error' => 'Invalid parameter "Overrides/1/color": a hexadecimal colour code (6 symbols) is expected.'
				]
			],
			// Time shift field validation.
			[
				[
					'Overrides' => [
						[
							'options' => [
								'Time shift'
							]
						]
					],
					'error' => 'Invalid parameter "Overrides/1/timeshift": cannot be empty.'
				]
			],
			[
				[
					'Overrides' => [
						[
							'time_shift' => 'abc',
							'options' => [
								'Time shift'
							]
						]
					],
					'error' => 'Invalid parameter "Overrides/1/timeshift": a time unit is expected.'
				]
			],
			[
				[
					'Overrides' => [
						[
							'time_shift' => '5.2',
							'options' => [
								'Time shift'
							]
						]
					],
					'error' => 'Invalid parameter "Overrides/1/timeshift": a time unit is expected.'
				]
			],
			[
				[
					'Overrides' => [
						[
							'time_shift' => '10000d',
							'options' => [
								'Time shift'
							]
						]
					],
					'error' => 'Invalid parameter "Overrides/1/timeshift": value must be one of -788400000-788400000.'
				]
			],
			[
				[
					'Overrides' => [
						[
							'time_shift' => '999999999999999999999999999',
							'options' => [
								'Time shift'
							]
						]
					],
					'error' => 'Invalid parameter "Overrides/1/timeshift": a number is too large.'
				]
			],
			// Validation of second override set.
			[
				[
					'Overrides' => [
						[
							'options' => [
								['Width', '5']
							]
						],
						[
							'options' => [
								['Width', '10']
							]
						]
					],
					'error' => 'Invalid parameter "Overrides/2/hosts": cannot be empty.'
				]
			],
			[
				[
					'Overrides' => [
						[
							'options' => [
								['Width', '5']
							]
						],
						[
							'item' => 'Two item',
							'options' => [
								['Width', '10']
							]
						]
					],
					'error' => 'Invalid parameter "Overrides/2/hosts": cannot be empty.'
				]
			],
			[
				[
					'Overrides' => [
						[
							'options' => [
								['Width', '5']
							]
						],
						[
							'host' => 'Two host',
							'options' => [
								['Width', '10']
							]
						]
					],
					'error' => 'Invalid parameter "Overrides/2/items": cannot be empty.'
				]
			],
			[
				[
					'Overrides' => [
						[
							'options' => [
								['Width', '5']
							]
						],
						[
							'host' => 'Two host',
							'item' => 'Two item'
						]
					],
					'error' => 'Invalid parameter "Overrides/2": at least one override option must be specified.'
				]
			],
			[
				[
					'Overrides' => [
						[
							'options' => [
								['Width', '5']
							]
						],
						[
							'host' => 'Two host',
							'item' => 'Two item',
							'options' => [
								'Base colour'
							]
						]
					],
					'error' => 'Invalid parameter "Overrides/2/color": cannot be empty.'
				]
			],
			[
				[
					'Overrides' => [
						[
							'options' => [
								['Width', '5']
							]
						],
						[
							'host' => 'Two host',
							'item' => 'Two item',
							'time_shift' => 'abc',
							'options' => [
								'Time shift'
							]
						]
					],
					'error' => 'Invalid parameter "Overrides/2/timeshift": a time unit is expected.'
				]
			]
		];
	}

	/*
	 * Data provider for "Overrides" tab validation on creating.
	 */
	public function getOverridesValidationCreateData() {
		$data = [];

		// Add host and item values for tab "Data set" and "Overrides" for each data provider.
		foreach ($this->getOverridesValidationData() as $item) {
				$item[0]['Data set'] = [
					'host' => 'ЗАББИКС Сервер',
					'item' => 'Agent ping'
				];
				$item[0]['Overrides'][0] = array_merge($item[0]['Overrides'][0], [
					'host' => 'One host',
					'item' => 'One item'
				]);

			$data[] = $item;
		}

		return array_merge($data, [
			[
				[
					'Data set' => [
						'host' => 'ЗАББИКС Сервер',
						'item' => 'Agent ping'
					],
					'error' => 'Invalid parameter "Overrides/1/hosts": cannot be empty.'
				]
			],
			[
				[
					'Data set' => [
						'host' => 'ЗАББИКС Сервер',
						'item' => 'Agent ping'
					],
					'Overrides' => [
						'item' => '*',
					],
					'error' => 'Invalid parameter "Overrides/1/hosts": cannot be empty.'
				]
			],
			[
				[
					'Data set' => [
						'host' => 'ЗАББИКС Сервер',
						'item' => 'Agent ping'
					],
					'Overrides' => [
						'host' => ' ',
						'item' => '*'
					],
					'error' => 'Invalid parameter "Overrides/1/hosts": cannot be empty.'
				]
			],
			[
				[
					'Data set' => [
						'host' => 'ЗАББИКС Сервер',
						'item' => 'Agent ping'
					],
					'Overrides' => [
						'host' => '*'
					],
					'error' => 'Invalid parameter "Overrides/1/items": cannot be empty.'
				]
			],
			[
				[
					'Data set' => [
						'host' => 'ЗАББИКС Сервер',
						'item' => 'Agent ping'
					],
					'Overrides' => [
						'host' => '*',
						'item' => '*'
					],
					'error' => 'Invalid parameter "Overrides/1": at least one override option must be specified.'
				]
			]
		]);
	}

	/*
	 * Data provider for "Overrides" tab validation on updating.
	 */
	public function getOverridesValidationUpdateData() {
		$data = [];

		// Add existing widget name for each case in data provider.
		foreach ($this->getOverridesValidationData() as $item) {
				$item[0]['Widget name'] = 'Test cases for update';

			$data[] = $item;
		}

		// Add additional validation cases.
		return array_merge($data, [
			[
				[
					'Widget name' => 'Test cases for update',
					'remove_override_options' => true,
					'error' => 'Invalid parameter "Overrides/1": at least one override option must be specified.'
				]
			],
			[
				[
					'Widget name' => 'Test cases for update',
					'Overrides' => [
						'host' => '',
						'item' => ''
					],
					'error' => 'Invalid parameter "Overrides/1/hosts": cannot be empty.'
				]
			],
			[
				[
					'Widget name' => 'Test cases for update',
					'Overrides' => [
						'host' => ''
					],
					'error' => 'Invalid parameter "Overrides/1/hosts": cannot be empty.'
				]
			],
			[
				[
					'Widget name' => 'Test cases for update',
					'Overrides' => [
						'item' => '',
					],
					'error' => 'Invalid parameter "Overrides/1/items": cannot be empty.'
				]
			]
		]);
	}

	/**
	 * Check "Overrides" tab validation.
	 *
	 * @dataProvider getOverridesValidationCreateData
	 * @dataProvider getOverridesValidationUpdateData
	 */
	public function testGraphWidget_OverridesValidation($data) {
		$this->validate($data, 'Overrides');
	}

	public static function getCreateData() {
		return [
			// Mandatory fields only.
			[
				[
					'Data set' => [
						'host' => '*',
						'item' => '*'
					],
					'check_form' => true
				]
			],
			/* Add Width, Fill and Missing data fields in overrides, which are disabled in data set tab.
			 * Fill enabled right Y-axis fields.
			 */
			[
				[
					'main_fields' => [
						'Name' => 'Test graph widget',
						'Refresh interval' => '10 seconds'
					],
					'Data set' => [
						'host' => 'Zabbix*, one, two',
						'item' => 'Agetn*, one, two, one',
						'fields' => [
							'Draw' => 'Points',
							'Y-axis' => 'Right'
						]
					],
					'Time period' => [
						'Set custom time period' => true,
						'From' => 'now-1w',
						'To' => 'now'
					],
					'Axes' => [
						'id:righty_min' => '-15',
						'id:righty_max' => '155.5',
						'id:righty_units' => 'Static',
						'id:righty_static_units' => 'MB'
					],
					'Overrides' => [
						'host' => 'One host',
						'item' => 'One item',
						'options' => [
							['Width', '2'],
							['Fill', '2'],
							['Missing data', 'None']
						]
					],
					'check_form' => true
				]
			],
			/* Boundary values.
			 * Creation with disabled axes and legend. Enabled Problems, but empty fields in problems tab.
			 */
			[
				[
					'main_fields' => [
						'Name' => 'Test boundary values',
						'Refresh interval' => '10 minutes'
					],
					'Data set' => [
						[
							'host' => '*',
							'item' => '*',
							'fields' => [
								'Width' => '0',
								'Transparency' => '0',
								'Fill' => '0',
								'Missing data' => 'Treat as 0',
								'Time shift' => '-788400000'
							]
						],
						[
							'host' => 'Two host',
							'item' => 'Two item',
							'fields' => [
								'Y-axis' => 'Right',
								'Width' => '10',
								'Transparency' => '10',
								'Fill' => '10',
								'Missing data' => 'Connected',
								'Time shift' => '788400000'
							]
						]
					],
					'Displaying options' => [
						'History data selection' => 'Trends'
					],
					'Time period' => [
						'Set custom time period' => true,
						'From' => 'now-59s',
						'To' => 'now'
					],
					'Axes' => [
						'Left Y' => false,
						'Right Y' => false,
						'X-Axis' => false
					],
					'Legend' => [
						'Show legend' => false
					],
					'Problems' => [
						'fields' => [
							'Show problems' => true
						]
					],
					'Overrides' => [
						'host' => 'One host',
						'item' => 'One item',
						'time_shift' => '788400000',
						'options' => [
							'Time shift',
							['Draw', 'Staircase'],
							['Missing data', 'Treat as 0']
						]
					],
					'check_form' => true
				]
			],
			// All posible fields.
			[
				[
					'main_fields' => [
						'Name' => 'Graph widget with all filled fields',
						'Refresh interval' => 'No refresh'
					],
					'Data set' => [
						[
							'host' => 'One host',
							'item' => 'One item',
							'fields' => [
								'Base colour' => '009688',
								'Draw' => 'Staircase',
								'Width' => '10',
								'Transparency' => '10',
								'Fill' => '10',
								'Missing data' => 'Connected',
								'Time shift' => '0'
							]
						],
						[
							'host' => 'Two host',
							'item' => 'Two item',
							'fields' => [
								'Base colour' => '000000',
								'Y-axis' => 'Right',
								'Draw' => 'Points',
								'Point size' => '1',
								'Transparency' => '0',
								'Time shift' => '-1s'
							]
						]
					],
					'Displaying options' => [
						'History data selection' => 'History'
					],
					'Time period' => [
						'Set custom time period' => true,
						'From' => '2018-11-15 08',
						'To' => '2018-11-15 14:20'
					],
					'Axes' => [
						'id:lefty_min' => '5',
						'id:lefty_max' => '15.5',
						'id:righty_min' => '-15',
						'id:righty_max' => '-5',
						'id:lefty_units' => 'Static',
						'id:lefty_static_units' => 'MB',
						'id:righty_units' => 'Static'
					],
					'Legend' => [
						'Number of rows' => '5'
					],
					'Problems' => [
						'fields' => [
							'Show problems' => true,
							'Selected items only' => false,
							'Problem hosts' => [
								'values' => ['Simple form test host', 'ЗАББИКС Сервер'],
								'context' => 'Zabbix servers'
							],
							'Severity' => ['Information', 'Average'],
							'Problem' => '2_trigger_*',
							'Tags' => 'Or'
						],
						'tags' => [
							['name' => 'server', 'value' => 'selenium', 'operator' => 'Equals'],
							['name' => 'Street', 'value' => 'dzelzavas']
						]
					],
					'Overrides' => [
						[
							'host' => 'One host',
							'item' => 'One item',
							'color' => '000000',
							'time_shift' => '-5s',
							'options' => [
								'Base colour',
								['Width', '0'],
								['Draw', 'Line'],
								['Transparency', '0'],
								['Fill', '0'],
								['Point size', '1'],
								['Missing data', 'None'],
								['Y-axis', 'Right'],
								'Time shift'
							]
						],
						[
							'host' => 'Two host',
							'item' => 'Two item',
							'color' => 'FFFFFF',
							'time_shift' => '5s',
							'options' => [
								'Base colour',
								['Width', '1'],
								['Draw', 'Points'],
								['Transparency', '2'],
								['Fill', '3'],
								['Point size', '4'],
								['Missing data', 'Connected'],
								['Y-axis', 'Left'],
								'Time shift'
							]
						]
					],
					'check_form' => true
				]
			]
		];
	}

	/**
	 * Check graph widget successful creation.
	 *
	 * @dataProvider getCreateData
	 */
	public function testGraphWidget_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=103');
		$form = $this->openGraphWidgetConfiguration();

		$this->fillForm($data, $form);
		$form->parents('class:overlay-dialogue-body')->one()->query('tag:output')->asMessage()->waitUntilNotVisible();
		$form->submit();
		$this->saveGraphWidget(CTestArrayHelper::get($data, 'main_fields.Name', 'Graph'));

		// Check valuse in created widget.
		if (CTestArrayHelper::get($data, 'check_form', false)) {
			$this->openGraphWidgetConfiguration(CTestArrayHelper::get($data, 'main_fields.Name', 'Graph'));
			$this->checkWidgetForm($data);
		}
	}

	public static function getUpdateData() {
		return [
			// Mandatory fields only.
			[
				[
					'Data set' => [
						'host' => 'updated*',
						'item' => '*updated'
					],
					'check_form' => true
				]
			],
			/* Add Width, Fill and Missing data fields in overrides, which are disabled in data set tab.
			 * Fill fields for enabled right Y-axis.
			 */
			[
				[
					'main_fields' => [
						'Refresh interval' => '10 seconds'
					],
					'Data set' => [
						'host' => 'Zabbix*, update, two',
						'item' => 'Agetn*, update, two, update',
						'fields' => [
							'Draw' => 'Points',
							'Y-axis' => 'Right'
						]
					],
					'Time period' => [
						'Set custom time period' => true,
						'From' => 'now-1w',
						'To' => 'now'
					],
					'Axes' => [
						'id:righty_min' => '-15',
						'id:righty_max' => '155.5',
						'id:righty_units' => 'Static',
						'id:righty_static_units' => 'MB'
					],
					'Overrides' => [
						'host' => 'One host',
						'item' => 'One item',
						'options' => [
							['Width', '2'],
							['Fill', '2'],
							['Point size', '5'],
							['Missing data', 'None']
						]
					],
					'check_form' => true
				]
			],
			/* Boundary values.
			 * Update with disabled axes and legend. Enabled Problems, but left empty fields in problems tab.
			 */
			[
				[
					'main_fields' => [
						'Refresh interval' => '10 minutes'
					],
					'Data set' => [
						[
							'host' => '*',
							'item' => '*',
							'fields' => [
								'Draw' => 'Line',
								'Y-axis' => 'Left',
								'Width' => '0',
								'Transparency' => '0',
								'Fill' => '0',
								'Missing data' => 'Treat as 0',
								'Time shift' => '-788400000'
							]
						],
						[
							'host' => 'Two host',
							'item' => 'Two item',
							'fields' => [
								'Y-axis' => 'Right',
								'Width' => '10',
								'Transparency' => '10',
								'Fill' => '10',
								'Missing data' => 'Connected',
								'Time shift' => '788400000'
							]
						]
					],
					'Displaying options' => [
						'History data selection' => 'Trends'
					],
					'Time period' => [
						'Set custom time period' => true,
						'From' => 'now-59s',
						'To' => 'now'
					],
					'Axes' => [
						'Left Y' => false,
						'Right Y' => false,
						'X-Axis' => false
					],
					'Legend' => [
						'Show legend' => false
					],
					'Problems' => [
						'fields' => [
							'Show problems' => true
						]
					],
					'check_form' => true
				]
			],
			// All posible fields.
			[
				[
					'main_fields' => [
						'Name' => 'Update graph widget with all filled fields',
						'Refresh interval' => 'No refresh'
					],
					'Data set' => [
						[
							'host' => 'One host',
							'item' => 'One item',
							'fields' => [
								'Y-axis' => 'Left',
								'Base colour' => '009688',
								'Draw' => 'Staircase',
								'Width' => '10',
								'Transparency' => '10',
								'Fill' => '10',
								'Missing data' => 'Connected',
								'Time shift' => '0'
							]
						],
						[
							'host' => 'Two host',
							'item' => 'Two item',
							'fields' => [
								'Base colour' => '000000',
								'Y-axis' => 'Right',
								'Draw' => 'Points',
								'Point size' => '1',
								'Transparency' => '0',
								'Time shift' => '-1s'
							]
						]
					],
					'Displaying options' => [
						'History data selection' => 'History'
					],
					'Time period' => [
						'Set custom time period' => true,
						'From' => '2018-11-15 08',
						'To' => '2018-11-15 14:20'
					],
					'Axes' => [
						'Left Y' => true,
						'Right Y' => true,
						'X-Axis' => true,
						'id:lefty_min' => '5',
						'id:lefty_max' => '15.5',
						'id:righty_min' => '-15',
						'id:righty_max' => '-5',
						'id:lefty_units' => 'Static',
						'id:lefty_static_units' => 'MB',
						'id:righty_units' => 'Static'
					],
					'Legend' => [
						'Show legend' => true,
						'Number of rows' => '5'
					],
					'Problems' => [
						'fields' => [
							'Show problems' => true,
							'Selected items only' => false,
							'Problem hosts' => [
								'values' => ['ЗАББИКС Сервер', 'Simple form test host'],
								'context' => 'Zabbix servers'
							],
							'Severity' => ['Information', 'Average'],
							'Problem' => '2_trigger_*',
							'Tags' => 'Or'
						],
						'tags' => [
							['name' => 'server', 'value' => 'selenium', 'operator' => 'Equals'],
							['name' => 'Street', 'value' => 'dzelzavas']
						]
					],
					'Overrides' => [
						[
							'host' => 'One host',
							'item' => 'One item',
							'color' => '000000',
							'time_shift' => '-5s',
							'options' => [
								'Base colour',
								['Width', '0'],
								['Draw', 'Line'],
								['Transparency', '0'],
								['Fill', '0'],
								['Point size', '1'],
								['Missing data', 'None'],
								['Y-axis', 'Right'],
								'Time shift'
							]
						],
						[
							'host' => 'Two host',
							'item' => 'Two item',
							'color' => 'FFFFFF',
							'time_shift' => '5s',
							'options' => [
								'Base colour',
								['Width', '1'],
								['Draw', 'Points'],
								['Transparency', '2'],
								['Fill', '3'],
								['Point size', '4'],
								['Missing data', 'Connected'],
								['Y-axis', 'Left'],
								'Time shift'
							]
						]
					],
					'check_form' => true
				]
			]
		];
	}

	/**
	 * Check graph widget successful update.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testGraphWidget_Update($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=103');
		$form = $this->openGraphWidgetConfiguration('Test cases for update');

		$this->fillForm($data, $form);
		$form->parents('class:overlay-dialogue-body')->one()->query('tag:output')->asMessage()->waitUntilNotVisible();
		$form->submit();
		$this->saveGraphWidget(CTestArrayHelper::get($data, 'main_fields.Name', 'Test cases for update'));

		// Check valuse in updated widget.
		if (CTestArrayHelper::get($data, 'check_form', false)) {
			$this->openGraphWidgetConfiguration(CTestArrayHelper::get($data, 'main_fields.Name', 'Test cases for update'));
			$this->checkWidgetForm($data);
		}
	}

	/**
	 * Test update without any modification of graph widget data.
	 */
	public function testGraphWidget_SimpleUpdate() {
		$name = 'Test cases for simple update and deletion';
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=103');
		$form = $this->openGraphWidgetConfiguration($name);
		$form->submit();
		$this->saveGraphWidget($name);

		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	/**
	 * Fill graph widget form with provided data.
	 *
	 * @param array $data		data provider with fields values
	 * @param array $form		CFormElement
	 */
	private function fillForm($data, $form) {
		$form->fill(CTestArrayHelper::get($data, 'main_fields', []));

		$this->fillDatasets(CTestArrayHelper::get($data, 'Data set', []));

		$tabs = ['Displaying options', 'Time period', 'Axes', 'Legend', 'Problems', 'Overrides'];
		foreach ($tabs as $tab) {
			if (!array_key_exists($tab, $data)) {
				continue;
			}

			$form->selectTab($tab);
			switch ($tab) {
				case 'Problems':
					CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);
					$form->fill(CTestArrayHelper::get($data['Problems'], 'fields', []));
					CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_TYPE);

					if (array_key_exists('tags', $data['Problems'])) {
						$this->setTags($data['Problems']['tags'], 'id:tags_table_tags');
					}
					break;

				case 'Overrides':
					$this->fillOverrides($data['Overrides']);
					break;

				default:
					$form->fill($data[$tab]);
					break;
			}
		}
	}

	/**
	 * Set field mapping and fill in non-standard fields.
	 */
	private function fillMappedFields($data, $mapping) {
		$form = $this->query('id:widget_dialogue_form')->asForm()->one();

		foreach ($mapping as $field => $item) {
			if (!array_key_exists($field, $data)) {
				continue;
			}

			if (!is_array($item)) {
				$item = ['selector' => $item, 'class' => CElement::class];
			}

			$form->query($item['selector'])->cast($item['class'])->one()->fill($data[$field]);
		}
	}

	/**
	 * Fill "Data sets" with specified data.
	 */
	private function fillDatasets($data_sets) {
		$form = $this->query('id:widget_dialogue_form')->asForm()->one();
		if ($data_sets) {
			if (CTestArrayHelper::isAssociative($data_sets)) {
				$data_sets = [$data_sets];
			}

			$last = count($data_sets) - 1;
			// Amount of data sets on frontend.
			$count_sets = $form->query('xpath://li[contains(@class, "list-accordion-item")]')->all()->count();

			foreach ($data_sets as $i => $data_set) {
				$mapping = [
					'host' => 'id:ds_'.$i.'_hosts',
					'item' => 'id:ds_'.$i.'_items'
				];
				$this->fillMappedFields($data_set, $mapping);

				$form->fill(CTestArrayHelper::get($data_set, 'fields', []));

				// Open next dataset, if it exist on frontend.
				if ($i !== $last) {
					if ($i + 1 < $count_sets) {
						$i += 2;
						$form->query('xpath:(//li[contains(@class, "list-accordion-item")])['.$i.']//button')->one()->click();
					}
					// Press "Add new data set" button, except for last data set.
					else {
						$form->query('button:Add new data set')->one()->click();
					}

					$form->invalidate();
				}
			}
		}
	}

	/**
	 * Fill "Overrides" with specified data.
	 */
	private function fillOverrides($overrides) {
		$form = $this->query('id:widget_dialogue_form')->asForm()->one();

		// Check if override already exist in list, if not, add new override.
		$items = $form->query('class:overrides-list-item')->all();
		if ($items->count() === 0) {
			$form->query('button:Add new override')->one()->click();
		}

		if ($overrides) {
			if (CTestArrayHelper::isAssociative($overrides)) {
				$overrides = [$overrides];
			}

			$last = count($overrides) - 1;

			foreach ($overrides as $i => $override) {
				$mapping = [
					'options' => [
						'selector' => 'xpath://button[@data-row='.CXPathHelper::escapeQuotes($i).']',
						'class' => CPopupButtonElement::class
					],
					'host' => 'id:or_'.$i.'_hosts',
					'item' => 'id:or_'.$i.'_items',
					'color' => 'id:or_'.$i.'__color_',
					'time_shift' => 'name:or['.$i.'][timeshift]',
				];

				$this->fillMappedFields($override, $mapping);

				// Press "Add new override" button, except for last override set and if in data provider exist only one set.
				if ($i !== $last) {
					$form->query('button:Add new override')->one()->click();
				}
			}
		}
	}

	/**
	 * Check widget field values after creating or updating.
	 */
	private function checkWidgetForm($data) {
		$form = $this->query('id:widget_dialogue_form')->asForm()->one();

		// Check values in "Data set" tab.
		if (CTestArrayHelper::isAssociative($data['Data set'])) {
			$data['Data set'] = [$data['Data set']];
		}

		$last = count($data['Data set']) - 1;

		foreach ($data['Data set'] as $i => $data_set) {
			// Check host and item fields values.
			$mapping = [
				'host' => 'id:ds_'.$i.'_hosts',
				'item' => 'id:ds_'.$i.'_items'
			];
			foreach ($mapping as $field => $id) {
				$element = $this->query($id)->one();
				$this->assertEquals($data_set[$field], $element->getValue());
			}

			// Check other field values.
			$form->checkValue(CTestArrayHelper::get($data_set, 'fields', []));

			// Open next data set, if exist.
			if ($i !== $last) {
				$i += 2;
				$form->query('xpath:(//li[contains(@class, "list-accordion-item")])['.$i.']//button')->one()->click();
				$form->invalidate();
			}
		}

		$tabs = ['Displaying options', 'Time period', 'Axes','Legend'];
		foreach ($tabs as $tab) {
			if (array_key_exists($tab, $data)) {
				$form->selectTab($tab);
				$form->checkValue($data[$tab]);
			}
		}

		if (array_key_exists('Problems', $data)) {
			$form->selectTab('Problems');
			if (CTestArrayHelper::get($data, 'Problems.fields.Problem hosts', false)) {
				$element = $this->query('id:problemhosts')->one();
				$this->assertEquals(implode(', ', $data['Problems']['fields']['Problem hosts']['values']), $element->getValue());
				unset($data['Problems']['fields']['Problem hosts']);
			}
			$form->checkValue(CTestArrayHelper::get($data, 'Problems.fields', []));

			if (array_key_exists('tags', $data['Problems'])) {
				$this->assertTags($data['Problems']['tags'], 'id:tags_table_tags');
			}
		}

		if (array_key_exists('Overrides', $data)) {
			$form->selectTab('Overrides');
			if (CTestArrayHelper::isAssociative($data['Overrides'])) {
				$data['Overrides'] = [$data['Overrides']];
			}

			foreach ($data['Overrides'] as $i => $override) {
				$mapping = [
					'host' => 'id:or_'.$i.'_hosts',
					'item' => 'id:or_'.$i.'_items',
					'color' => 'id:or_'.$i.'__color_',
					'time_shift' => 'name:or['.$i.'][timeshift]',
				];
				foreach ($mapping as $field => $selector) {
					if (!array_key_exists($field, $override)) {
						continue;
					}
					$element = $this->query($selector)->one();
					$this->assertEquals($override[$field], $element->getValue());
				}

				// Check values of override options in data provider and in widget, except color and time shift fields.
				if (array_key_exists('options', $override)) {
					$i++;
					$list = $this->query('xpath:(//ul[@class="overrides-options-list"])['.$i.']')->one();
					$options = $list->query('xpath:.//span[@data-option]')->all();
					$options_text = $options->asText();

					// Check number of override options in data provider and in widget.
					$values = array_filter($override['options'], function ($option) {
						return (is_array($option) && count($option) === 2);
					});
					$this->assertEquals(count($values), $options->count());

					foreach ($override['options'] as $option) {
						if (is_array($option) && count($option) === 2) {
							$this->assertContains(implode(': ', $option), $options_text);
						}
					}
				}
			}
		}
	}

	public static function getDashboardCancelData() {
		return [
			// dd new graph widget.
			[
				[
					'main_fields' => [
						'Name' => 'Add new graph widget and cancle dashboard update'
					],
					'Data set' => [
						'host' => 'Zabbix*, new widget',
						'item' => 'Agetn*, new widget'
					]
				]
			],
			// Update existing graph widget.
			[
				[
					'Existing widget' => 'Test cases for simple update and deletion',
					'main_fields' => [
						'Name' => 'Update graph widget and cancel dashboard'
					],
					'Data set' => [
						'host' => 'Update widget, cancel dashboard update',
						'item' => 'Update widget'
					]
				]
			]
		];
	}

	/**
	 * Update existing widget or create new and cancel dashboard update.
	 *
	 * @dataProvider getDashboardCancelData
	 */
	public function testGraphWidget_cancelDashboardUpdate($data) {
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=103');
		$form = $this->openGraphWidgetConfiguration(CTestArrayHelper::get($data, 'Existing widget', []));
		$form->fill(CTestArrayHelper::get($data, 'main_fields', []));
		$this->fillDatasets($data['Data set']);
		$form->submit();

		// Check added or updated graph widget.
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget(CTestArrayHelper::get($data, 'main_fields.Name', 'Graph'));
		$widget->getContent()->query('class:svg-graph')->waitUntilVisible();

		$dashboard->cancelEditing();

		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public static function getWidgetCancelData() {
		return [
			// Add new graph widget.
			[
				[
					'main_fields' => [
						'Name' => 'Cancel widget create'
					],
					'Data set' => [
						'host' => 'Cancel create',
						'item' => 'Cancel create',
					]
				]
			],
			// Update existing graph widget.
			[
				[
					'Existing widget' => 'Test cases for simple update and deletion',
					'main_fields' => [
						'Name' => 'Cancel widget update'
					],
					'Data set' => [
						'host' => 'Cancel update',
						'item' => 'Cancel update',
					]
				]
			]
		];
	}

	/**
	 * Cancel update of existing widget or cancel new widget creation and save dashboard.
	 *
	 * @dataProvider getDashboardCancelData
	 */
	public function testGraphWidget_cancelWidgetEditing($data) {
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=103');
		$form = $this->openGraphWidgetConfiguration(CTestArrayHelper::get($data, 'Existing widget', []));
		$form->fill($data['main_fields']);
		$this->fillDatasets($data['Data set']);
		$overlay = $this->query('xpath://div[contains(@class, "overlay-dialogue")][@data-dialogueid="widgetConfg"]')
				->asOverlayDialog()->one();
		$overlay->close();

		// Check canceled graph widget.
		$dashboard = CDashboardElement::find()->one();
		// If test fails and widget isn't canceled, need to wait until widget appears on the dashboard.
		sleep(2);
		$this->assertTrue($dashboard->query('xpath:.//div[contains(@class, "dashbrd-grid-widget-head")]/h4[text()='.
				CXPathHelper::escapeQuotes($data['main_fields']['Name']).']')->one(false) === null);
		$dashboard->save();

		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	/**
	 * Test deleting of graph widget.
	 */
	public function testGraphWidget_Delete() {
		$name = 'Test cases for simple update and deletion';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=103');
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->edit()->getWidget($name);
		$this->assertEquals(true, $widget->isEditable());
		$widget->delete();

		$dashboard->save();
		$this->page->waitUntilReady();
		$message = CMessageElement::find()->waitUntilPresent()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());

		// Check that widget is not present on dashboard and in DB.
		$this->assertTrue($dashboard->query('xpath:.//div[contains(@class, "dashbrd-grid-widget-head")]/h4[text()='.
				CXPathHelper::escapeQuotes($name).']')->one(false) === null);
		$sql = 'SELECT * FROM widget_field wf LEFT JOIN widget w ON w.widgetid=wf.widgetid'.
				' WHERE w.name='.zbx_dbstr($name);
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	/**
	 * Test disabled fields in "Data set" tab.
	 */
	public function testGraphWidget_DatasetDisabledFields() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=103');
		$form = $this->openGraphWidgetConfiguration();

		foreach (['Line', 'Points', 'Staircase'] as $option) {
			$form->fill(['Draw' => $option]);

			// Check the disabled fields depending on selected Draw option.
			switch ($option) {
				case 'Line':
				case 'Staircase':
					$fields = ['Point size'];
					break;

				case 'Points':
					$fields = ['Width', 'Fill', 'Missing data'];
					break;
			}

			$this->assertEnabledFields($fields, false);
		}
	}

	/*
	 * Test "From" and "To" fields in tab "Time period" by check/uncheck "Set custom time period".
	 */
	public function testGraphWidget_TimePeriodDisabledFields() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=103');
		$form = $this->openGraphWidgetConfiguration();
		$form->selectTab('Time period');

		$fields = ['From', 'To'];
		$this->assertEnabledFields($fields, false);

		$form->fill(['Set custom time period' => true]);
		$this->assertEnabledFields($fields, true);
	}

	/*
	 * Test enable/disable "Number of rows" field by check/uncheck "Show legend".
	 */
	public function testGraphWidget_LegendDisabledFields() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=103');
		$form = $this->openGraphWidgetConfiguration();
		$form->selectTab('Legend');
		$this->assertEnabledFields('Number of rows');
		$form->fill(['Show legend' => false]);
		$this->assertEnabledFields('Number of rows', false);
	}

	public function testGraphWidget_ProblemsDisabledFields() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=103');
		$form = $this->openGraphWidgetConfiguration();
		$form->selectTab('Problems');

		$fields = ['Selected items only', 'Severity', 'Problem', 'Tags', 'Problem hosts'];
		$tag_elements = [
			'id:evaltype',				// Tag type.
			'id:tags_0_tag',			// Tag name.
			'id:tags_0_operator_0',		// Tag operator.
			'id:tags_0_value',			// Tag value
			'id:tags_0_remove',			// Tag remove button.
			'id:tags_add'				// Tagg add button.
		];
		$this->assertEnabledFields(array_merge($fields, $tag_elements), false);

		// Set "Show problems" and check that fields enabled now.
		$form->fill(['Show problems' => true]);
		$this->assertEnabledFields(array_merge($fields, $tag_elements), true);
	}

	public static function getAxesDisabledFieldsData() {
		return [
			[
				[
					'Data set' => [
						'Y-axis' => 'Right'
					]
				]
			],
			[
				[
					'Data set' => [
						'Y-axis' => 'Left'
					]
				]
			],
			// Both Y-axis are enabled, if in data set selected Left axis, but in Overrides selected Right.
			[
				[
					'Data set' => [
						'Y-axis' => 'Right'
					],
					'Overrides' => [
						'options' => [
							['Y-axis', 'Left']
						]
					]
				]
			],
			[
				[
					'Data set' => [
						'Y-axis' => 'Left'
					],
					'Overrides' => [
						'options' => [
							['Y-axis', 'Right']
						]
					]
				]
			]
		];
	}

	/**
	 * Check that the axes fields are disabled depending on the selected axis in Data set and in Overrides.
	 *
	 * @dataProvider getAxesDisabledFieldsData
	 */
	public function testGraphWidget_AxesDisabledFields($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=103');
		$form = $this->openGraphWidgetConfiguration();

		$form->fill($data['Data set']);
		$axis = $data['Data set']['Y-axis'];

		if (array_key_exists('Overrides', $data)) {
			$axis = 'Both';
			$form->selectTab('Overrides');
			$this->fillOverrides($data['Overrides']);
		}

		$form->selectTab('Axes');
		$lefty_fields = ['id:lefty', 'id:lefty_min', 'id:lefty_max', 'id:lefty_units'];
		$righty_fields = ['id:righty', 'id:righty_min', 'id:righty_max', 'id:righty_units'];

		switch ($axis) {
			case 'Right':
				$lefty_fields[] = 'id:lefty_static_units';
				$this->assertEnabledFields($lefty_fields, false);
				$this->assertEnabledFields($righty_fields, true);
				$this->assertFalse($this->query('id:righty_static_units')->one()->isEnabled());
				break;

			case 'Left':
				$righty_fields[] = 'id:righty_static_units';
				$this->assertEnabledFields($lefty_fields, true);
				$this->assertEnabledFields($righty_fields, false);
				$this->assertFalse($this->query('id:lefty_static_units')->one()->isEnabled());
				break;

			case 'Both';
				$this->assertEnabledFields($lefty_fields, true);
				$this->assertEnabledFields($righty_fields, true);
				$this->assertFalse($this->query('id:righty_static_units')->one()->isEnabled());
				$this->assertFalse($this->query('id:lefty_static_units')->one()->isEnabled());
				break;
		}
	}

	/**
	 * Check that fields are enabled or disabled.
	 *
	 * @param array $fields			array of checked fields
	 * @param boolean $enabled		fields state are enabled
	 * @param boolean $id			is used field id instead of field name
	 */
	private function assertEnabledFields($fields, $enabled = true) {
		$form = $this->query('id:widget_dialogue_form')->asForm()->one();

		if (!is_array($fields)) {
			$fields = [$fields];
		}

		foreach ($fields as $field) {
			$this->assertTrue($form->getField($field)->isEnabled($enabled));
		}
	}

	/*
	 * Debug button sometime overlaps widget edit icon, after widget creation.
	 */
	public static function setDebugMode($value) {
		DBexecute('UPDATE usrgrp SET debug_mode='.zbx_dbstr($value).' WHERE usrgrpid=7');
	}

	public function disableDebugMode() {
		self::setDebugMode(0);
	}

	public static function enableDebugMode() {
		self::setDebugMode(1);
	}
}