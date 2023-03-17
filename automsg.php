<?php
 /**
* AutoMsg Profile  - Joomla Module 
* Version			: 2.0
* Package			: Joomla 4.x.x
* copyright 		: Copyright (C) 2023 ConseilGouz. All rights reserved.
* license    		: http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
*/
defined('JPATH_BASE') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;
use Joomla\Database\ParameterType;
use Joomla\CMS\Plugin\CMSPlugin;

class plgUserautomsg extends CMSPlugin {
protected $db;	
	/**
	 * @param	string	The context for the data
	 * @param	int		The user id
	 * @param	object
	 * @return	boolean
	 * @since	1.6
	 */
	function onContentPrepareData($context, $data)
	{
		// Check we are manipulating a valid form.
		if (!in_array($context, array('com_users.profile','com_users.registration','com_users.user','com_admin.profile'))){
			return true;
		}
 
		$userId = isset($data->id) ? $data->id : 0;
 
		// Load the profile data from the database.
        $db    = $this->db;
        $query = $db->getQuery(true)
                 ->select(
                        [
                            $db->quoteName('profile_key'),
                            $db->quoteName('profile_value'),
                        ]
                    )
                ->from($db->quoteName('#__user_profiles'))
                ->where($db->quoteName('user_id') . ' = :userid')
                ->where($db->quoteName('profile_key') . ' LIKE '.$db->quote('profile_automsg.%'))
                ->order($db->quoteName('ordering'))
                ->bind(':userid', $userId, ParameterType::INTEGER);

        $db->setQuery($query);
        $results = $db->loadRowList();
 
		// Merge the profile data.
		$data->profile_automsg = array();
		foreach ($results as $v) {
			$k = str_replace('profile_automsg.', '', $v[0]);
			$data->profile_automsg[$k] = json_decode($v[1], true);
		}
 
		return true;
	}
 
	/**
	 * @param	JForm	The form to be altered.
	 * @param	array	The associated data for the form.
	 * @return	boolean
	 * @since	1.6
	 */
	function onContentPrepareForm($form, $data)
	{
		// Load user_profile plugin language
		$lang = Factory::getLanguage();
		$lang->load('plg_user_profile_automsg', JPATH_ADMINISTRATOR);
 
		if (!($form instanceof Form)) {
			// $this->_subject->setError('JERROR_NOT_A_FORM');
			return false;
		}
		// Check we are manipulating a valid form.
		if (!in_array($form->getName(), array('com_users.profile', 'com_users.registration','com_users.user','com_admin.profile'))) {
			return true;
		}
		if ($form->getName()=='com_users.profile')
		{
			// Add the profile fields to the form.
			Form::addFormPath(dirname(__FILE__).'/profiles');
			$form->loadFile('profile', false);
 
			// Toggle whether the something field is required.
			if ($this->params->get('profile-require_something', 1) > 0) {
				$form->setFieldAttribute('something', 'required', $this->params->get('profile-require_something') == 2, 'profile_automsg');
			} else {
				$form->removeField('something', 'profile_automsg');
			}
		}
 
		//In this example, we treat the frontend registration and the back end user create or edit as the same. 
		elseif ($form->getName()=='com_users.registration' || $form->getName()=='com_users.user' )
		{		
			// Add the registration fields to the form.
			Form::addFormPath(dirname(__FILE__).'/profiles');
			$form->loadFile('profile', false);
 
			// Toggle whether the something field is required.
			if ($this->params->get('register-require_something', 1) > 0) {
				$form->setFieldAttribute('something', 'required', $this->params->get('register-require_something') == 2, 'profile_automsg');
			} else {
				$form->removeField('something', 'profile_automsg');
			}
		}			
	}
 
	function onUserAfterSave($data, $isNew, $result, $error)
	{
		$userId	= (int)$data['id'];
 
		if ($userId && $result && isset($data['profile_automsg']) && (count($data['profile_automsg']))) {
			$db = $this->db;
			try	{
				$db->setQuery(
					$db->getQuery(true)
							->delete($db->quoteName('#__user_profiles'))
							->where($db->quoteName('user_id').' = :userId AND profile_key LIKE \'profile_automsg.%\'')
							->bind(':userId', $userId, ParameterType::INTEGER)
							)->execute();
			} catch (ExecutionFailureException $e) {
				// Do nothing.
			}

			$order	= 1;
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__user_profiles'));
			foreach ($data['profile_automsg'] as $k => $v) {
                $query->values(
                    implode(
                        ',',
                        $query->bindArray(
                            [
                                $userId,
                                'profile_automsg.' . $k,
                                json_encode($v),
                                $order++,
                            ],
                            [
                                ParameterType::INTEGER,
                                ParameterType::STRING,
                                ParameterType::STRING,
                                ParameterType::INTEGER,
                            ]
                        )
                    )
                );
			}
            $db->setQuery($query);
            $db->execute();
		}
 
		return true;
	}
 
	/**
	 * Remove all user profile information for the given user ID
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param	array		$user		Holds the user data
	 * @param	boolean		$success	True if user was succesfully stored in the database
	 * @param	string		$msg		Message
	 */
	function onUserAfterDelete($user, $success, $msg)
	{
		if (!$success) {
			return false;
		}
 
		$userId	= $user['id'];
 
		if ($userId) {
			try	{
				$this->db->setQuery(
					$this->db->getQuery(true)
							->delete($this->db->quoteName('#__user_profiles'))
							->where($this->db->quoteName('user_id').' = :userId AND profile_key LIKE \'profile_automsg.%\'')
							->bind(':userId', $userId, ParameterType::INTEGER)
							)->execute();
			} catch (ExecutionFailureException $e) {
				// Do nothing.
			}
		}
 
		return true;
	}
 
 
 }