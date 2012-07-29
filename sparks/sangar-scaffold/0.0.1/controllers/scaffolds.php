<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Scaffolds extends CI_Controller
{

	function __construct()
	{
		parent::__construct();
	}

	function index()
	{

		//Rules for validation
		$this->_set_rules();

		//create control variables
		$data['title'] = "Scaffolds";
		$data['updType'] = 'create';

		//validate the fields of form
		if ($this->form_validation->run() == FALSE) 
		{			
			//load the view and the layout
			$this->load->view('scaffolds_create', $data);
		}
		else
		{

			$data = array(
						'controller_name'		=>	$this->input->post('controller_name', TRUE),
						'model_name'			=>	$this->input->post('model_name', TRUE),
						'scaffold_code'			=>	$this->input->post('scaffold_code', TRUE),
						'scaffold_delete_bd'	=>	$this->input->post('scaffold_delete_bd', TRUE ),
						'scaffold_bd' 			=>	$this->input->post('scaffold_bd', TRUE ),
						'scaffold_routes' 		=>	$this->input->post('scaffold_routes', TRUE ),
						'scaffold_menu' 		=>	$this->input->post('scaffold_menu', TRUE ),

						'create_controller' 	=>	$this->input->post('create_controller', TRUE ),
						'create_model' 			=>	$this->input->post('create_model', TRUE ),
						'create_view_create' 	=>	$this->input->post('create_view_create', TRUE ),
						'create_view_list' 		=>	$this->input->post('create_view_list', TRUE ),
					);

			$result = $this->sangar_scaffolds->create($data);

			if ($result === TRUE)
			{	
				$this->session->set_flashdata('message', array( 'type' => 'success', 'text' => lang('scaffolds_ok') ));

				redirect("".$this->input->post('controller_name'));
			}
			else
			{
				$this->session->set_flashdata('message', array( 'type' => 'error', 'text' => $result ));
				
				$this->load->view('/scaffolds/create', $data);
			}
	  	} 
	}


	private function _set_rules($type = 'create', $id = NULL)
	{
		//validate form input
		$this->form_validation->set_rules('controller_name', 'Controller Name', 'required|xss_clean');
		$this->form_validation->set_rules('model_name', 'Controller Name', 'required|xss_clean');
		$this->form_validation->set_rules('scaffold_code', 'Scaffold Code', 'required|xss_clean');
		$this->form_validation->set_error_delimiters('<br /><span class="error">', '</span>');
	}	


}