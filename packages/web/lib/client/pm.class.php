<?php
/**
 * Powermanagement Client information
 *
 * PHP version 5
 *
 * @category Powermanagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Powermanagement Client information
 *
 * @category Powermanagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PM extends FOGClient
{
    /**
     * Sends the powermanagement stuff in json format
     *
     * @return array
     */
    public function json()
    {
        $actions = self::getSubObjectIDs(
            'PowerManagement',
            array(
                'id' => $this->Host->get('powermanagementtasks'),
                'onDemand' => '1'
            ),
            'action'
        );
        $action = '';
        if (in_array('shutdown', $actions)) {
            $action = 'shutdown';
        } elseif (in_array('reboot', $actions)) {
            $action = 'reboot';
        }
        $PMTasks = self::getClass('PowerManagementManager')
            ->find(
                array(
                    'hostID' => $this->Host->get('id'),
                    'onDemand' => array(
                        '0',
                        0,
                        null,
                        ''
                    )
                )
            );
        $data = array(
            'onDemand' => $action,
            'tasks' => array(),
        );
        foreach ((array)$PMTasks as &$PMTask) {
            if (!$PMTask->isValid()) {
                continue;
            }
            $min = trim($PMTask->get('min'));
            $hour = trim($PMTask->get('hour'));
            $dom = trim($PMTask->get('dom'));
            $month = trim($PMTask->get('month'));
            $dow = trim($PMTask->get('dow'));
            $cron = sprintf('%s %s %s %s %s', $min, $hour, $dom, $month, $dow);
            $data['tasks'][] = array(
                'cron' => $cron,
                'action' => $PMTask->get('action'),
            );
        }
        return $data;
    }
}
