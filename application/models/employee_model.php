<?php
class Employee_model extends CI_Model {

	public function __construct()
	{
		$this->current_time();	
	}

	function current_time()
	{
		$timezone = 'Asia/Manila';
		if(function_exists('date_default_timezone_set')) {
			date_default_timezone_set($timezone);
		}
	}
	
	public function get_employees($employee_id = FALSE)
	{
		if ($employee_id === FALSE)
		{
			$query = $this->db->get_where('employee_information',array('active'=>'true'));
			return $query->result_array();
		}

		$this->db->select('*');
		$this->db->from('employee_information');
		$this->db->join('status', 'status.employee_id = employee_information.employee_id','inner');
		$this->db->join('employee_type', 'employee_type.employee_type_id = employee_information.employee_type_id','inner');
		$this->db->join('job_position', 'job_position.job_position_id = employee_information.job_position_id','inner');
		$this->db->where('employee_information.employee_id',$employee_id);
		$this->db->where('employee_information.active','true');
		$this->db->where('status.active','true');
		$query = $this->db->get();
		
		return $query->row_array();
	}

	public function get_inactive_employees($employee_id = FALSE)
	{
		if ($employee_id === FALSE)
		{
			$query = $this->db->get_where('employee_information',array('active'=>'false'));
			return $query->result_array();
		}
		$this->db->select('*');
		$this->db->from('employee_information');
		$this->db->join('status', 'status.employee_id = employee_information.employee_id','inner');
		$this->db->join('employee_type', 'employee_type.employee_type_id = employee_information.employee_type_id','inner');
		$this->db->where('employee_information.employee_id',$employee_id);
		$this->db->where('employee_information.active','false');
		$this->db->where('status.active','true');
		$query = $this->db->get();
		return $query->row_array();
	}
	
	public function get_types()
	{
		$query = $this->db->get('employee_type');
		return $query->result_array();
	}
	
// Inactive employee functions ------------	
	public function restore_employee($id)
	{
		$data = array (
		'active' => 'true');
		
		return $this->db->update('employee_information', $data,"employee_id = $id");
	}
	
	public function delete_employee($id)
	{
		$end_date = date('M d Y');
		$data = array (
		'active' => 'false',
		'username' => NULL,
		'password' => NULL);

		$data2 = array(
		'end_date' => $end_date
		);
		
		$this->db->trans_start();

		$this->db->update('status', $data2,array('employee_id' => $id,'active' => 'true'));
		$this->db->update('employee_information', $data,"employee_id = $id");

		$this->db->trans_complete();
	}
	
	public function permanent_delete_employee($id)
	{
		$this->db->delete('employee_information', array('employee_id' => $id)); 
		
	}
	
	public function fetch_inactive_employees($limit, $start) 
	{
        $this->db->limit($limit, $start);
		$this->db->order_by('first_name'); 
        $query = $this->db->get_where('employee_information',array('active'=>'false'));		
 
       return $query->result_array();
	}
	
//end of inactive employee functions----------------
	
//regular employee functions------------------------	
	public function set_employee()
	{
		$type = $this->input->post('etype');
		$position = $this->input->post('etitle');
		
		if($type == 2){
			$position = 26;
		}

		//set employee
		$data = array(
			'first_name' => ucwords(strtolower($this->input->post('fname'))),
			'middle_name' => ucwords(strtolower($this->input->post('mname'))),
			'last_name' => ucwords(strtolower($this->input->post('lname'))),
			'gender' => $this->input->post('gender'),
			'address' =>  ucwords(strtolower($this->input->post('address'))),
			'birthdate' => $this->input->post('birthdate'),
			'mobile_num' => $this->input->post('mobile'),
			'work_email' => $this->input->post('wemail'),
			'employee_type_id' => $this->input->post('etype'),
			'job_position_id' => $position,
			'managerial_exp' => $this->input->post('manager_xp'),
			'user_type_id' => 2,
			'username' => $this->input->post('username'),
			'password' => md5($this->input->post('password'))	
			);

		$this->db->trans_start();
	
		$this->db->insert('employee_information', $data);

		$query = $this->db->query("SELECT * FROM employee_information ORDER BY employee_id DESC LIMIT 1")->row_array();	
		
		//set employee status
		$data2 = array(
			'employee_id' => $query['employee_id'],
			'start_date' => $this->input->post('startdate'),
			'employee_status' => $this->input->post('estatus')
			);
		
		$query2 = $this->db->query("SELECT * FROM academic_year WHERE active = TRUE")->row_array();
		
		//create employee record
		$data3 = array(
			'employee_id' => $query['employee_id'],
			'vl' => 0,
			'vl_lwop' => 0,
			'sl' => 0,
			'sl_lwop' => 0,
			'others' => 0,
			'academic_year_id' => $query2['academic_year_id']
			);

		$query3 = $this->db->query("SELECT * FROM year WHERE active = TRUE")->row_array();
		
		//create employee medical record
		$data4 = array(
			'employee_id' => $query['employee_id'],
			'benefit_consumed' => 0,
			'year_id' => $query3['year_id']
			);
		
		//create employee type history
		$data5 = array(
			'employee_id' => $query['employee_id'],
			'employee_type_id' => $this->input->post('etype'),
			'type_start_date' => $this->input->post('startdate')
			);

		$this->db->insert('status', $data2);
		$this->db->insert('employee_record', $data3);
		$this->db->insert('employee_medical_record', $data4);
		$this->db->insert('employee_type_history', $data5);

		$this->db->trans_complete();
	}

	function get_length_of_service($employee_id)
	{
		$query = $this->db->get_where('status',array('employee_id'=>$employee_id,'active'=>'true'));
		$row = $query->row_array();

		$date1 = new DateTime($row['start_date']);
		$date2 = new DateTime(date('M d Y'));
		$interval = $date1->diff($date2);
		$months = ($interval->format('%y') * 12) + $interval->format('%m');
		return $months;
	}

	function set_employee_status()
	{
		$query = $this->db->query("SELECT * FROM employee_information ORDER BY employee_id DESC LIMIT 1")->row_array();	
		$data = array(
			'employee_id' => $query['employee_id'],
			'start_date' => $this->input->post('startdate'),
			'employee_status' => $this->input->post('estatus')
			);

		return $this->db->insert('status', $data);

	}

	function get_employee_record($id)
	{
		$this->db->select('*');
		$this->db->from('employee_record');
		$this->db->where('employee_id',$id);
		$this->db->where('active','true');
		$query = $this->db->get();

		return $query->row_array();
	}

	function get_employee_medical_record($id)
	{
		$this->db->select('*');
		$this->db->from('employee_medical_record');
		$this->db->where('employee_id',$id);
		$this->db->where('active','true');
		$query = $this->db->get();

		return $query->row_array();
	}

	function search()
	{
		$match =  strtolower($this->input->post('namesearch'));

		$search_query = "SELECT *, emp.first_name||' '||emp.last_name AS full_name FROM employee_information as emp, status as estatus, employee_type as etype 
		WHERE emp.employee_id = estatus.employee_id AND
		emp.employee_type_id = etype.employee_type_id AND 
		(LOWER(emp.first_name) LIKE '%$match%' ESCAPE '!' OR LOWER(emp.last_name) LIKE '%$match%' ESCAPE '!' OR  LOWER(emp.first_name||' '|| emp.last_name) LIKE '%$match%' ESCAPE '!') AND emp.employee_type_id is not null AND emp.active = TRUE AND estatus.active = TRUE";

		$query = $this->db->query($search_query);
		return $query->result_array();
	}
	
	public function employee_count() 
	{
        $this->db->where('active', 'true');
		$this->db->from('employee_information');
		return $this->db->count_all_results();
    }
	
	public function inactive_employee_count() 
	{
        $this->db->where('active', 'false');
		$this->db->from('employee_information');
		return $this->db->count_all_results();
    }
	
	public function fetch_employees($limit, $start) {
        $this->db->limit($limit, $start);
		
        $this->db->select('*');
		$this->db->from('employee_information');
		$this->db->join('status', 'status.employee_id = employee_information.employee_id','inner');
		$this->db->join('employee_type', 'employee_type.employee_type_id = employee_information.employee_type_id','inner');
		$this->db->where('employee_information.active','true');
		$this->db->where('employee_information.user_type_id',2);
		$this->db->where('status.active','true');
		$this->db->order_by('first_name');
		$query = $this->db->get();

		return $query->result_array();
   }

   	//change pass
   	function edit_password($userid){
		$new_pass = md5($this->input->post('new'));
		$data = array(
			'password' => $new_pass
			);
		
		$this->db->update('employee_information',$data, "employee_id = $userid");
   	}

   	function edit_bday($userid){

		$data = array(
			'birthdate' => $this->input->post('birthdate')
			);	

		$this->db->update('employee_information',$data, "employee_id = $userid");
   	}

   	//edit employee type
   	function edit_type($userid){
   		$type = $this->input->post('etype');
   		$position = $this->input->post('eposition_new');
   		$this->db->select('*');
		$this->db->from('employee_information');
		$this->db->where('employee_information.employee_id',$userid);
		$query = $this->db->get();
		$result = $query->row_array();

		if($result['employee_type_id'] == $type){
			return FALSE;
		}

		if($type == 2){
			$position = 26;
		}

		$data = array(
			'employee_type_id' => $this->input->post('etype'),
			'job_position_id' => $position
			);

		$data2 = array(
			'type_end_date' => $this->input->post('startdate'),
			'active' => 'false'
			);
		
		$data3 = array(
			'employee_id' => $userid,
			'employee_type_id' => $this->input->post('etype'),
			'type_start_date' => $this->input->post('startdate')
			);

		$this->db->trans_start();

		$this->db->update('employee_information',$data, "employee_id = $userid");
		$this->db->update('employee_type_history',$data2,  array('employee_id' => $userid, 'active' => 'true'));
		$this->db->insert('employee_type_history', $data3);

		$this->db->trans_complete();
   	}

   	//edit status
   	function edit_status($userid){
   		$status = $this->input->post('estatus');
   		$end_date = date('M d Y');

   		$this->db->select('*');
		$this->db->from('status');
		$this->db->where('status.employee_id',$userid);
		$this->db->where('status.active','true');
		$query = $this->db->get();
		$result = $query->row_array();

		if($result['employee_status'] == $status){
			return FALSE;
		}

		$data = array(
			'active' => 'false',
			'end_date' => $end_date
			);

		$start_date = $result['start_date'];
		
		$data2 = array(
			'employee_id' => $userid,
			'employee_status' => $this->input->post('estatus'),
			'start_date' => $start_date,
			'active' => 'true'
			);

		$this->db->trans_start();

		$this->db->update('status',$data, array('employee_id' => $userid, 'active' => 'true'));
		$this->db->insert('status', $data2);

		$this->db->trans_complete();
   	}


   	//edit position
   	function edit_position($userid){
   		$position = $this->input->post('eposition');
   		
   		$this->db->select('*');
		$this->db->from('employee_information');
		$this->db->where('employee_information.employee_id',$userid);
		$query = $this->db->get();
		$result = $query->row_array();

		if($result['job_position_id'] == $position){
			return FALSE;
		}
		
		$data = array(
			'job_position_id' => $this->input->post('eposition')
			);

		$this->db->update('employee_information',$data, array('employee_id' => $userid));
   	}

   	//edit managerial exp
   	function edit_managerial_exp($userid){
		
		$data = array(
			'managerial_exp' => ucwords($this->input->post('manager_xp')),
			);

		$this->db->update('employee_information',$data, "employee_id = $userid");
   	}

   	//get job titles
   	public function get_job_positions()
	{
		$this->db->select('*');
		$this->db->from('job_position');
		$this->db->order_by('position');
		$this->db->where('position !=','Teacher');
		$query = $this->db->get();
		return $query->result_array();
	}

	function get_employee_position($employee_id)
	{
		$this->db->select('*');
		$this->db->from('employee_information');
		$this->db->join('job_position', 'job_position.job_position_id = employee_information.job_position_id','left');
		$this->db->where('employee_id',$employee_id);
		$this->db->where('active','true');
		$query = $this->db->get();

		return $query->row_array();
	}

	function get_educ_high($employee_id)
	{
		$query = $this->db->query("SELECT * FROM education,educational_attainment,education_detail 
			WHERE education.educational_attainment_id = educational_attainment.educational_attainment_id 
			AND education_detail.education_detail_id = educational_attainment.education_detail_id
			AND education.employee_id = '$employee_id' 
			AND (education_detail.detail = 'Graduated' OR education_detail.detail = '1 yr course' OR education_detail.detail = '2 yr course' OR education_detail.detail = '3 yr course') 
			ORDER BY educational_attainment.education_level_id DESC LIMIT 1")->row_array();
		return $query;
	}

	function get_work_points($etype)
	{
		$this->db->select('*');
		$this->db->from('work_experience');
		$this->db->join('work_type_experience', 'work_type_experience.work_type_experience_id = work_experience.work_type_experience_id','inner');
		$this->db->where('work_experience.employee_type_id',$etype);
		$query = $this->db->get();
		return $query->result_array();
	}

	function get_exam_base_points($etype)
	{
		$this->db->select('*');
		$this->db->from('base_points');
		$this->db->join('employee_type', 'employee_type.employee_type_id = base_points.employee_type_id','inner');
		$this->db->join('criteria', 'criteria.criteria_id = base_points.criteria_id','inner');
		$this->db->where('employee_type.employee_type_id',$etype);
		$this->db->where('criteria.criteria_description','Certifications or Board/Government Examinations Passed');
		$query = $this->db->get();
		return $query->row_array();
	}

	function get_work_ici($employee_id)
	{
		$this->db->select('*');
		$this->db->from('employee_type_history');
		$this->db->join('employee_type', 'employee_type.employee_type_id = employee_type_history.employee_type_id','inner');
		$this->db->where('employee_id',$employee_id);
		$this->db->order_by('type_start_date','desc'); 
		$query = $this->db->get();
		return $query->result_array();
	}

	function get_employee_certification_summary($employee_id)
	{		
		$this->db->select('*');
		$this->db->from('certification_board_acquired');
		$this->db->join('employee_information', 'employee_information.employee_id = certification_board_acquired.employee_id','inner');
		$this->db->join('certification_board', 'certification_board.certification_board_id = certification_board_acquired.certification_board_id','inner');
		$this->db->join('certification_board_type', 'certification_board_type.certification_board_type_id = certification_board.certification_board_type_id','inner');
		$this->db->join('certification_board_detail', 'certification_board_detail.certification_board_detail_id = certification_board.certification_board_detail_id','inner');
		$this->db->where('employee_information.employee_id',$employee_id);
		//$this->db->where('base_leave.active','true');
		$this->db->order_by('certification_board_acquired.certification_board_id','asc'); 
		$query = $this->db->get();
		$result = $query->result_array();

		return $result;
	}

	function get_employee_work_summary($employee_id)
	{
		$this->db->select('*');
		$this->db->from('work');
		$this->db->join('employee_information', 'employee_information.employee_id = work.employee_id','inner');
		$this->db->join('employee_type', 'employee_information.employee_type_id = employee_type.employee_type_id','inner');
		$this->db->join('work_type_experience', 'work_type_experience.work_type_experience_id = work.work_type_experience_id','inner');
		$this->db->where('work.employee_id',$employee_id);
		$this->db->order_by('work.previous_work_start_date','desc'); 
		$query = $this->db->get();
		return $query->result_array();
	}

	function get_multiplier($criteria,$etype)
	{
		$this->db->select('*');
		$this->db->from('weight_multiplier');
		$this->db->join('employee_type', 'employee_type.employee_type_id = weight_multiplier.employee_type_id','inner');
		$this->db->join('criteria', 'criteria.criteria_id = weight_multiplier.criteria_id','inner');
		$this->db->where('employee_type.employee_type_id',$etype);
		$this->db->where('criteria.criteria_description',$criteria);
		$query = $this->db->get();
		return $query->row_array();
	}

	function get_educ_total($employee_id){
		$educ_total = 0;

		$this->db->select('*');
		$this->db->from('education');
		$this->db->join('employee_information', 'employee_information.employee_id = education.employee_id','inner');
		$this->db->join('educational_attainment', 'educational_attainment.educational_attainment_id = education.educational_attainment_id','inner');
		$this->db->join('education_level', 'education_level.education_level_id = educational_attainment.education_level_id','inner');
		$this->db->join('education_detail', 'education_detail.education_detail_id = educational_attainment.education_detail_id','inner');
		$this->db->where('employee_information.employee_id',$employee_id);
		$this->db->order_by('education.educational_attainment_id','asc'); 
		$query = $this->db->get();
		$educ_summary = $query->result_array();

		if(count($educ_summary) <1){
		$educ_total = 0;
		}
		else{
			foreach($educ_summary as $summary):
				$educ_total = $educ_total + $summary['educ_points'];
			endforeach;
		}
		return $educ_total;
	}

	function get_work_total($employee_id,$emp_type,$etype){
		$staff_work_duration = 0;
		$faculty_teach_duration = 0;
		$faculty_industry_duration = 0;

		//get work experience
		$current_work_summary = $this->get_work_ici($employee_id);

		foreach($current_work_summary as $summary):
			$start_date = new DateTime($summary['type_start_date']);
			if($summary['type_end_date'] == null){
				$end_date = new DateTime(date('M d Y'));
			}
			else{
				$end_date = new DateTime($summary['type_end_date']);
			}
			$interval = $start_date->diff($end_date);
			$length_of_service = ($interval->format('%y') * 12) + $interval->format('%m');

			//add work length to staff
			if($emp_type == 'Staff'){
				$staff_work_duration = $staff_work_duration + $length_of_service;
			}
			//add work length to faculty
			else{
				if($summary['employee_type'] == 'Staff'){ // faculty industry
					$faculty_industry_duration = $faculty_industry_duration + $length_of_service;
				}
				else{
					$faculty_teach_duration = $faculty_teach_duration + $length_of_service;
				}
			}
		endforeach;

		$previous_work_summary = $this->get_employee_work_summary($employee_id);

		foreach($previous_work_summary as $summary):
			//add work length to staff
			if($emp_type == 'Staff'){
				$staff_work_duration = $staff_work_duration + $length_of_service;
			}
			//add work length to faculty
			else{
				if($summary['work_type'] == 'Industry'){ // faculty industry
					$faculty_industry_duration = $faculty_industry_duration + $summary['work_duration'];
				}
				else{	//faculty teaching
					$faculty_teach_duration = $faculty_teach_duration + $summary['work_duration'];
				}
			}
		endforeach;

		$work_points = $this->get_work_points($etype);

		//total work duration
		if($emp_type == 'Staff'){
			$total_work_duration = $staff_work_duration;
		}
		else{ 
			$total_work_duration = $faculty_teach_duration + (int) ($faculty_industry_duration/2);
		}

		foreach($work_points as $points):
			if($total_work_duration >= $points['work_min_months'] && $total_work_duration < $points['work_max_months']){
				$work_total = $points['work_points'];
			}
		endforeach;

		return $work_total;
	}

	function get_cert_total($employee_id,$etype){
	//get certificate or exams
		$certification_total = 0;

		$cert_summary = $this->get_employee_certification_summary($employee_id);

		foreach($cert_summary as $summary):
			$certification_total = $certification_total + $summary['cert_points'];
		endforeach;
		$base_cert_points = $this->get_exam_base_points($etype);

		if(count($cert_summary) > 0){
			$base = $base_cert_points['base_points'];
		}
		else{
			$base = 0;
		}

		return $certification_total = $certification_total + $base;
	}

	function get_lowest_faculty_rank()
	{
		$this->db->select('*');
		$this->db->from('faculty_rank_classification');
		$this->db->join('faculty_rank', 'faculty_rank.faculty_rank_id = faculty_rank_classification.faculty_rank_id','inner');
		$this->db->join('level', 'level.level_id = faculty_rank_classification.level_id','left');
		$this->db->join('education_level', 'education_level.education_level_id = faculty_rank_classification.education_requirement_id','inner');
		$this->db->where('faculty_rank.faculty_rank_description','Junior Instructor');
		$this->db->where('level.level','I');
		$query = $this->db->get();
		
		return $query->row_array();
	}

	function get_lowest_staff_rank()
	{
		$this->db->select('*');
		$this->db->from('staff_rank_classification');
		$this->db->join('staff_job_grade', 'staff_job_grade.staff_job_grade_id = staff_rank_classification.staff_job_grade_id','inner');
		$this->db->join('level', 'level.level_id = staff_rank_classification.level_id','left');
		$this->db->where('staff_job_grade.job_description','Skilled');
		$this->db->where('level.level','I');
		$query = $this->db->get();
		
		return $query->row_array();
	}

	function get_staff_rank_unskilled()
	{
		$this->db->select('*');
		$this->db->from('staff_rank_classification');
		$this->db->join('staff_job_grade', 'staff_job_grade.staff_job_grade_id = staff_rank_classification.staff_job_grade_id','inner');
		$this->db->join('level', 'level.level_id = staff_rank_classification.level_id','left');
		$this->db->where('staff_job_grade.job_description','Unskilled');
		$query = $this->db->get();
		
		return $query->row_array();
	}

	function get_staff_rank_semi_skilled()
	{
		$this->db->select('*');
		$this->db->from('staff_rank_classification');
		$this->db->join('staff_job_grade', 'staff_job_grade.staff_job_grade_id = staff_rank_classification.staff_job_grade_id','inner');
		$this->db->join('level', 'level.level_id = staff_rank_classification.level_id','left');
		$this->db->where('staff_job_grade.job_description','Semi-skilled');
		$query = $this->db->get();
		
		return $query->row_array();
	}

	function get_staff_rank_management()
	{
		$this->db->select('*');
		$this->db->from('staff_rank_classification');
		$this->db->join('staff_job_grade', 'staff_job_grade.staff_job_grade_id = staff_rank_classification.staff_job_grade_id','inner');
		$this->db->join('level', 'level.level_id = staff_rank_classification.level_id','left');
		$this->db->where('staff_job_grade.staff_job_grade_id','8');
		$query = $this->db->get();
		
		return $query->row_array();
	}

	function total_teach_duration($employee_id,$emp_type){
		$staff_work_duration = 0;
		$faculty_teach_duration = 0;
		$faculty_industry_duration = 0;

		//get work experience
		$current_work_summary = $this->get_work_ici($employee_id);

		foreach($current_work_summary as $summary):
			$start_date = new DateTime($summary['type_start_date']);
			if($summary['type_end_date'] == null){
				$end_date = new DateTime(date('M d Y'));
			}
			else{
				$end_date = new DateTime($summary['type_end_date']);
			}
			$interval = $start_date->diff($end_date);
			$length_of_service = ($interval->format('%y') * 12) + $interval->format('%m');

			//add work length to staff
			if($emp_type == 'Staff'){
				$staff_work_duration = $staff_work_duration + $length_of_service;
			}
			//add work length to faculty
			else{
				if($summary['employee_type'] == 'Staff'){ // faculty industry
					$faculty_industry_duration = $faculty_industry_duration + $length_of_service;
				}
				else{
					$faculty_teach_duration = $faculty_teach_duration + $length_of_service;
				}
			}
		endforeach;

		$previous_work_summary = $this->get_employee_work_summary($employee_id);

		foreach($previous_work_summary as $summary):
			//add work length to staff
			if($emp_type == 'Staff'){
				$staff_work_duration = $staff_work_duration + $length_of_service;
			}
			//add work length to faculty
			else{
				if($summary['work_type'] == 'Industry'){ // faculty industry
					$faculty_industry_duration = $faculty_industry_duration + $summary['work_duration'];
				}
				else{	//faculty teaching
					$faculty_teach_duration = $faculty_teach_duration + $summary['work_duration'];
				}
			}
		endforeach;

		return $faculty_teach_duration;
	}

	function get_rank_reference($etype)
	{
		if($etype == "Staff"){
			$this->db->select('*');
			$this->db->from('staff_rank_classification');
			$this->db->join('staff_job_grade', 'staff_job_grade.staff_job_grade_id = staff_rank_classification.staff_job_grade_id','inner');
			$this->db->join('level', 'level.level_id = staff_rank_classification.level_id','left');
			$this->db->order_by('staff_rank_classification.staff_rank_classification_id'); 
			$query = $this->db->get();
		
			return $query->result_array();
		}

		$this->db->select('*');
		$this->db->from('faculty_rank_classification');
		$this->db->join('faculty_rank', 'faculty_rank.faculty_rank_id = faculty_rank_classification.faculty_rank_id','inner');
		$this->db->join('level', 'level.level_id = faculty_rank_classification.level_id','left');
		$this->db->join('education_level', 'education_level.education_level_id = faculty_rank_classification.education_requirement_id','inner');
		$this->db->order_by('faculty_rank_classification.faculty_rank_classification_id'); 
		$query = $this->db->get();
		
		return $query->result_array();
	}

	function get_staff_rank_reference()
	{
		$this->db->select('*');
		$this->db->from('staff_rank_classification');
		$this->db->join('staff_job_grade', 'staff_job_grade.staff_job_grade_id = staff_rank_classification.staff_job_grade_id','inner');
		$this->db->join('level', 'level.level_id = staff_rank_classification.level_id','left');
		$this->db->order_by('staff_rank_classification.staff_rank_classification_id');
		$this->db->where('staff_rank_classification.min_point_range IS NOT NULL', null);
		$this->db->where('staff_rank_classification.max_point_range IS NOT NULL', null);
		$query = $this->db->get();
		
		return $query->result_array();
	}
}