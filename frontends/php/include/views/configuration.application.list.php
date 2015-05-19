<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

$applicationWidget = (new CWidget())->setTitle(_('Applications'));

$createForm = (new CForm('get'))->cleanItems();

$controls = (new CList())->
	addItem(array(_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()))->
	addItem(array(_('Host').SPACE, $this->data['pageFilter']->getHostsCB()));

// append host summary to widget header
if (empty($this->data['hostid'])) {
	$createButton = new CSubmit('form', _('Create application (select host first)'));
	$createButton->setEnabled(false);
	$controls->addItem($createButton);
}
else {
	$controls->addItem(new CSubmit('form', _('Create application')));

}

$applicationWidget->setControls($createForm);


$createForm->addItem($controls);
$applicationWidget->setControls($createForm);

$applicationWidget->addItem(get_header_host_table('applications', $this->data['hostid']));

// create form
$applicationForm = new CForm();
$applicationForm->setName('applicationForm');

// create table
$applicationTable = new CTableInfo();
$applicationTable->setHeader(array(
	new CColHeader(
		new CCheckBox('all_applications', null, "checkAll('".$applicationForm->getName()."', 'all_applications', 'applications');"),
		'cell-width'),
	($this->data['hostid'] > 0) ? null : _('Host'),
	make_sorting_header(_('Application'), 'name', $this->data['sort'], $this->data['sortorder']),
	_('Show'),
	$data['showInfoColumn'] ? _('Info') : null
));

$current_time = time();

foreach ($this->data['applications'] as $application) {
	$info_icons = [];

	// inherited app, display the template list
	if ($application['templateids'] && !empty($application['sourceTemplates'])) {
		$name = [];

		CArrayHelper::sort($application['sourceTemplates'], ['name']);

		foreach ($application['sourceTemplates'] as $template) {
			$name[] = new CLink($template['name'], 'applications.php?hostid='.$template['hostid'],
				ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_GREY
			);
			$name[] = ', ';
		}
		array_pop($name);
		$name[] = NAME_DELIMITER;
		$name[] = $application['name'];

		$info_icons[] = '';
	}
	elseif ($application['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $application['discoveryRule']) {
		$name = [new CLink(CHtml::encode($application['discoveryRule']['name']),
			'disc_prototypes.php?parent_discoveryid='.$application['discoveryRule']['itemid'],
			ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_ORANGE
		)];
		$name[] = NAME_DELIMITER.$application['name'];

		if ($application['applicationDiscovery']['ts_delete']) {
			$icon_warning = new CDiv(SPACE, 'status_icon iconwarning');

			// Check if application should've been deleted in the past.
			if ($current_time > $application['applicationDiscovery']['ts_delete']) {
				$icon_warning->setHint(_s(
					'The application is not discovered anymore and will be deleted the next time discovery rule is processed.'
				));
			}
			else {
				$icon_warning->setHint(_s(
					'The application is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
					zbx_date2age($application['applicationDiscovery']['ts_delete']),
					zbx_date2str(DATE_FORMAT, $application['applicationDiscovery']['ts_delete']),
					zbx_date2str(TIME_FORMAT, $application['applicationDiscovery']['ts_delete'])
				));
			}

			$info_icons[] = $icon_warning;
		}
		else {
			$info_icons[] = '';
		}
	}
	else {
		$name = new CLink($application['name'],
			'applications.php?form=update&applicationid='.$application['applicationid'].
				'&hostid='.$application['hostid']
		);

		$info_icons[] = '';
	}

	$checkBox = new CCheckBox('applications['.$application['applicationid'].']', null, null,
		$application['applicationid']
	);
	$checkBox->setEnabled(!$application['discoveryRule']);

	$applicationTable->addRow([
		$checkBox,
		($this->data['hostid'] > 0) ? null : $application['host']['name'],
		$name,
		[
			new CLink(
				_('Items'),
				'items.php?'.
					'hostid='.$application['hostid'].
					'&filter_set=1'.
					'&filter_application='.urlencode($application['name'])
			),
			CViewHelper::showNum(count($application['items']))
		],
		$data['showInfoColumn'] ? $info_icons : null
	]);
}

zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');

// append table to form
$applicationForm->addItem(array(
	$applicationTable,
	$this->data['paging'],
	new CActionButtonList('action', 'applications',
		array(
			'application.massenable' => array('name' => _('Enable'), 'confirm' => _('Enable selected applications?')),
			'application.massdisable' => array('name' => _('Disable'),
				'confirm' => _('Disable selected applications?')
			),
			'application.massdelete' => array('name' => _('Delete'), 'confirm' => _('Delete selected applications?'))
		),
		$this->data['hostid']
	)
));

// append form to widget
$applicationWidget->addItem($applicationForm);

return $applicationWidget;
