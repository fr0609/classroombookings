<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Classroombookings. Hassle-free resource booking for schools. <http://classroombookings.com/>
 * Copyright (C) 2006-2011 Craig A Rodway <craig.rodway@gmail.com>
 *
 * This file is part of Classroombookings.
 * Classroombookings is licensed under the Affero GNU GPLv3 license.
 * Please see license-classroombookings.txt for the full license text.
 */

class Permissions extends Configure_Controller
{	


	function __construct()
	{
		parent::__construct();
		$this->load->model('security_model');
		$this->load->model('departments_model');
	}
	
	
	
	
	/**
	 * PAGE: Main roles & permission page
	 */
	function index()
	{
		$this->auth->check('permissions');
		
		$tabs = array();
		
		$roles_data['roles'] = $this->permissions_model->get_roles();
		$roles_data['weights']['max'] = $this->permissions_model->get_role_weight('max');
		$roles_data['weights']['min'] = $this->permissions_model->get_role_weight('min');
		// Get lists of stuff we need for adding a role
		$roles_data['groups'] = $this->security_model->get_groups_dropdown();
		$roles_data['departments'] = $this->departments_model->get_dropdown();
		$roles_data['users'] = $this->security_model->get_users_dropdown();
		
		$tabs[] = array(
			'id' => 'roles',
			'title' => 'Roles',
			'view' => $this->load->view('permissions/tab_roles_index', $roles_data, true),
		);
		
		$tabs[] = array(
			'id' => 'role_assignments',
			'title' => 'Role Assignments',
			'view' => ''
		);
		
		$tabs[] = array(
			'id' => 'permissions',
			'title' => 'Permissions',
			'view' => '',
		);
		
		$body['tabs'] = $tabs;
		$body['active_tab'] = 'roles';
		
		$data['title'] = 'Roles &amp; Permissions';
		//$data['submenu'] = $this->menu_model->permissions();
		$data['body'] = $this->load->view('parts/tabs', $body, true);
		
		//$data['body'] .= $this->load->view('permissions/index', null, true);
		
		$data['js'] = array('js/tristate-checkbox.js');
		
		$this->page($data);
	}
	
	
	
	
	/*
	 * Roles
	 * =====
	 */
	
	
	
	
	/**
	 * PAGE: Add a new role
	 */
	function save_role()
	{
		$role_id = $this->input->post('role_id');

		$this->form_validation->set_rules('role_id', 'Role ID');
		$this->form_validation->set_rules('name', 'Name', 'required|max_length[20]|trim');
		$this->form_validation->set_rules('weight', 'Weight', 'numeric');
		$this->form_validation->set_error_delimiters('<li>', '</li>');

		if ($this->form_validation->run() == false)
		{
			// Validation failed - load required action depending on the state of user_id
			return $this->index();
		}
		else
		{
			// Validation OK
			$data['name'] = $this->input->post('name');

			if ($role_id == null)
			{
				$add = $this->permissions_model->add_role($data);
				if ($add == true)
				{
					$this->msg->add('info', 'The role has been added.');
				}
				else
				{
					$this->msg->add('err', 'Could not add the role: ' . $this->permissions_model->lasterr);
				}
			}
			else
			{
				// Updating existing role
				$edit = $this->permissions_model->edit_role($role_id, $data);
				if ($edit == true)
				{
					$this->msg->add('info', 'The role has been updated.');
				}
				else
				{
					$this->msg->add('err', 'Could not update the role: ' . $this->permissions_model->lasterr);
				}
			}

			// All done, redirect!
			redirect('permissions');

		}

	}
	
	
	
	
	function delete_role($role_id = null)
	{
		$this->auth->check('permissions');
		
		// Check if a form has been submitted; if not - show it to ask user confirmation
		if ($this->input->post('id'))
		{
			// Form has been submitted (so the POST value exists)
			// Call model function to delete user
			$delete = $this->permissions_model->delete_role($this->input->post('id'));
			if ($delete == false)
			{
				$this->msg->add('err', $this->permissions_model->lasterr, 'An error occured');
			}
			else
			{
				$this->msg->add('notice', 'The role has been deleted.');
			}
			// Redirect
			redirect('permissions');
		}
		else
		{
			// TODO: Add a check to make sure that specific roles aren't being deleted

			if ($role_id == null)
			{
				$data['title'] = 'Delete role';
				$data['body'] = $this->msg->err('Cannot find the role or no role ID supplied.');
			}
			else
			{
				// Get role info so we can present the confirmation page with a name
				$role = $this->permissions_model->get_role($role_id);
				if ($role == false)
				{
					$data['title'] = 'Delete role';
					$data['body'] = $this->msg->err('Could not find that role or no role ID given.');
				}
				else
				{
					// Initialise page
					$body['action'] = 'permissions/delete_role';
					$body['id'] = $role_id;
					$body['cancel'] = 'permissions';
					$body['text'] = 'If you delete this role, all users it would be applied to will have it removed.';
					$body['title'] = 'Are you sure you want to delete the role ' . $role->name . '?';
					$data['title'] = 'Delete ' . $role->name;
					$data['body'] = $this->load->view('parts/deleteconfirm', $body, true);
				}	// if role == false-else
			}	// if role_id == null-else
			
			$this->page($data);
			
		}	// if post(id) else
		
	}	// endfunction
	
	
	
	
	/**
	 * Assign role
	 */
	function assign_role()
	{
		$this->auth->check('permissions');
		
		$role_id = $this->input->post('role_id');
		$entity_type = $this->input->post('entity_type');
		$entity_id = null;
		
		$this->form_validation->set_rules('role_id', 'Role', 'required|integer|is_natural_no_zero');
		$this->form_validation->set_rules('entity_type', 'Entity type', 'exact_length[1]');
		
		// Validation for all entity IDs
		$valid_id = 'required|integer|is_natural_no_zero';
		
		// Add a rule depending on chosen entity type
		switch($entity_type)
		{
			case 'D':
				$this->form_validation->set_rules('department_id', 'Department', $valid_id);
				$entity_id = $this->input->post('department_id');
				$department = $this->departments_model->get($entity_id);
				$entity_type_name = 'department';
				$entity_name = $department->name;
				break;
			case 'G':
				$this->form_validation->set_rules('group_id', 'Group', $valid_id);
				$entity_id = $this->input->post('group_id');
				$group = $this->security_model->get_group($entity_id);
				$entity_type_name = 'group';
				$entity_name = $group->name;
				break;
			case 'U':
				$this->form_validation->set_rules('user_id', 'User', $valid_id);
				$entity_id = $this->input->post('user_id');
				$user = $this->security_model->get_user($entity_id);
				$entity_type_name = 'user';
				$entity_name = $user->displayname;
				break;
		}

		$this->form_validation->set_error_delimiters('<li>', '</li>');
		
		// Validate form
		if ($this->form_validation->run() == false)
		{
			// Validation failed - load required action depending on the state of user_id
			return $this->index();
		}
		else
		{
			$role = $this->permissions_model->get_role($role_id);
			
			$assign = $this->permissions_model->assign_role($role_id, $entity_type, $entity_id);
			$this->msg->add('notice', sprintf('The %s role has been assigned to %s %s.',
				$role->name, $entity_type_name, $entity_name));
			redirect('permissions');
		}
		
	}
	
	
	
	
	function move_role($direction = null, $role_id = null)
	{
		if (!$role_id) redirect('permissions');
		
		$role = $this->permissions_model->get_role($role_id);
		
		if (!$role) redirect('permissions');
		
		/*
		mysql_query("UPDATE menu SET menu_order = '$page_order' +1 WHERE id != '$menu_id' AND menu_order < '$page_order'");
		mysql_query("UPDATE menu SET menu_order = menu_order -1 WHERE id = '$menu_id'");    
	
	
		} else if ($_GET['do'] == 'down') {
	
		mysql_query("UPDATE menu SET menu_order = '$page_order' -1 WHERE id != '$menu_id'");
		mysql_query("UPDATE menu SET menu_order = menu_order +1 WHERE id = '$menu_id'");    
	
		*/
		
		$from = $role->weight;
		
		if ($direction == 'up')
		{
			$to = $from - 1;
		}
		elseif ($direction == 'down')
		{
			$to = $from + 1;
		}
		
		$sql = 'UPDATE roles SET weight = weight - 1 
				WHERE weight > ? AND weight <= ?';
		$query = $this->db->query($sql, array($from, $to));
		$sql = 'UPDATE roles SET weight = ? WHERE role_id = ?';
		$query = $this->db->query($sql, array($to, $role_id));
		
		redirect('permissions');
	}
	
	
	
	
	function save_role_order()
	{
		$role_weights = $this->input->post('role');
		print_r($role_weights);
		foreach($role_weights as $role_id => $weight)
		{
			$sql = 'UPDATE roles SET weight = ? WHERE role_id = ? LIMIT 1';
			$query = $this->db->query($sql, array($weight, $role_id));
		}
	}
	
	
	
	
	/**
	 * PAGE: Add a new permission entry
	 */
	 /*
	function add()
	{
		$this->auth->check('permissions');
		
		// Get lists of stuff we need
		$body['groups'] = $this->security_model->get_groups_dropdown();
		$body['departments'] = $this->departments_model->get_dropdown();
		$body['users'] = $this->security_model->get_users_dropdown();
		
		$body['permission_id'] = null;
		
		// List of all available permissions
		$body['available_permissions'] = $this->config->item('permissions');
		
		$data['js'] = array('js/tristate-checkbox.js');
		
		$data['title'] = 'Add permission entry';
		$data['body'] = $this->load->view('permissions/add', $body, true);
		$this->page($data);
	}
	*/
	
	
	
	/**
	 * Save the submitted permissions
	 */
	 /*
	function save_one()
	{
		$this->auth->check('permissions');
		
		$this->output->enable_profiler(true);
		
		$entity_type = $this->input->post('entity_type');
		$permission_id = $this->input->post('permission_id');
		$entity_id = null;
		
		$this->form_validation->set_rules('permission_id', 'Permission ID');
		$this->form_validation->set_rules('entity_type', 'Entity type', 'exact_length[1]');
		$this->form_validation->set_rules('permissions[]', 'Permissions');
		
		$valid_id = 'required|integer|is_natural_no_zero';
		
		// Add a rule depending on chosen entity type
		switch($entity_type)
		{
			case 'D':
				$this->form_validation->set_rules('department_id', 'Department', $valid_id);
				$entity_id = $this->input->post('department_id');
				break;
			case 'G':
				$this->form_validation->set_rules('group_id', 'Group', $valid_id);
				$entity_id = $this->input->post('group_id');
				break;
			case 'U':
				$this->form_validation->set_rules('user_id', 'User', $valid_id);
				$entity_id = $this->input->post('user_id');
				break;
		}
		
		$this->form_validation->set_error_delimiters('<li>', '</li>');
		
		// Validate form
		if ($this->form_validation->run() == FALSE)
		{
			// Validation failed - load required action depending on the state of user_id
			return ($permission_id == NULL) ? $this->add() : $this->edit($permission_id);
		}
		else
		{
			
			$data = array();
			$data['entity_type'] = $entity_type;
			$data['entity_id'] = $entity_id;
			$data['permissions'] = $this->input->post('permissions');
			
			if (empty($permission_id))
			{
				// Add new permission
				$ret = $this->permissions_model->add($data);
			}
			else
			{
				$ret = $this->permissions_model->edit($permission_id, $data);
			}
			
			echo "Done!";
			echo $ret;
			echo $this->permissions_model->lasterr;
			
			//redirect('permissions');
			
		}
		
	}
	*/
	
	
	
}


/* End of file controllers/permissions.php */