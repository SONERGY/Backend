<?php


require_once('rest.php');


class Api extends Rest
{
  public function __construct()
  {
    parent::__construct();
  }

  public function generate_token()
  {
    $email        = $this->validate_parameter("email", $this->param['email'], STRING);
    $password     = $this->validate_parameter("password", $this->param['password'], STRING);

    $auth_user = Signup::authenticate($email, $password);


    if (!$auth_user) {
      $this->throw_error(INVALID_USER_PASS, "Username or password is incorrect.");
    } else {
      $check = check_if_obj_exits_table2("user_reg", "email", $email, "password", $password);

      if ($check == true) {
        $token = generate_token_with_time($auth_user->id);
        $data = ['token' => $token, 'user' => $auth_user];
        $this->return_response(SUCCESS_RESPONSE, $data);
      } else {
        $this->throw_error(NOT_ACTIVE, array('status' => "INACTIVE", 'msg' => "Sorry this account has not been activated."));
      }
    }
  }

  public function activate()
  {
    $email_code    = $this->validate_parameter("code", $this->param['code'], STRING);

    if (check_if_obj_exits_table("user_reg", "email_auth_code", $email_code)) {

      $customer = Signup::findByDynamicFields("email_auth_code", $email_code);

      $customer->status = 1;

      if ($customer->save()) {
        // $msg =  "<br><p>Hi <b>" . $customer->first_name. " , ". $customer->last_name. "</b></p>
        // <p>The password for your Drivers Hood (".$customer->email.") has been successfully reset.\r\n</p>
        // <p>If you didn’t make this change or if you believe an unauthorized person has accessed your account,
        //   click <a href=\"http://drivershood.com/password/reset\">here</a> to reset your password immediately.";
        //   $html_email =  build_email($msg);

        // send_email(PASSWORD_RESET, $customer->email, $html_email);


        $data = ['message' =>  "Your account activation was successfully."];
        $this->return_response(SUCCESS_RESPONSE,  $data);
      } else {
        $this->throw_error(FAILED_QUERY,  " Unknown error has occurred. ");
      }
    } else {
      $this->throw_error(INVALID_USER_PASS,  "Authorization denied because you supplied invalid credentials.");
    }
  }

  public function create_survey_question()
  {
    $payload = $this->check_api();

    $creator =  check_user($payload);
    $survey_id          = $this->validate_parameter("survey id ", $this->param['survey_id'], INTEGER);
    $question_title     = $this->validate_parameter("question title", $this->param['question_title'], STRING);
    $question_type      = $this->validate_parameter("question type", $this->param['question_type'], STRING);

    if ($question_type == "radio" || $question_type == "checkbox") {
      $options    = $this->validate_parameter("question options", $this->param['options'], STRING);
    } else if ($question_type == "string") {
      $data_type    = $this->validate_parameter("data type", $this->param['data_type'], STRING);
    }

    $question  = new Questions();
    $question->survey_id = $survey_id;
    $question->survey_question_title =  $question_title;
    $question->survey_question_type = $question_type;
    $question->is_compulsory = true;

    if ($question_type == "radio" || $question_type == "checkbox") {
      $question->survey_question_options = $options;
    } else if ($question_type == "string") {
      $question->survey_data_type = $data_type;
    }

    if ($question->save()) {
      $update = Survey::findById($survey_id);
      if (!$update->is_survey_published) {
        if ($creator == $update->survey_creator) {

          if ($update->survey_creation_stage == 1) {
            $update->survey_creation_stage = 2;
            if ($update->save()) {
              // $data = ['message' =>  "Creation stage updated successfully.", 'id' =>  $update->id];
              // $this->return_response(SUCCESS_RESPONSE,  $data);

              $get_survey = getIncompleteSurveyWithId($creator);

              if ($get_survey == false) {
                $data = ['status' =>  false, 'message' =>  "Unknown Error Occurred."];
                $this->return_response(SUCCESS_RESPONSE,  $data);
              } else {
                $data = ['status' =>  true, 'message' =>  "Added successfully.", 'id' => null, 'survey' =>  extract_survey($get_survey), 'questions' => (extract_survey_question($get_survey))];
                $this->return_response(SUCCESS_RESPONSE,  $data);
              }
            } else {
              $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! 3");
            }
          } else {

            $get_survey = getIncompleteSurveyWithId($creator);
            if ($get_survey == false) {
              $data = ['status' =>  false, 'message' =>  "Unknown Error Occurred."];
              $this->return_response(SUCCESS_RESPONSE,  $data);
            } else {
              $data = ['status' =>  true, 'message' =>  "Added successfully.", 'id' => null, 'survey' =>  extract_survey($get_survey), 'questions' => (extract_survey_question($get_survey))];
              $this->return_response(SUCCESS_RESPONSE,  $data);
            }
          }
        } else {
          $this->throw_error(FAILED_QUERY,  " You are not the owner of this survey. Thanks. ");
        }
      } else {
        $this->throw_error(FAILED_QUERY,  " Can't edit already published survey. Thanks. ");
      }
    }
  }

  // Survey APIs

  public function check_incomplete_survey()
  {
    $payload = $this->check_api();

    $creator =  check_user($payload);

    $get_survey = getIncompleteSurveyWithId($creator);

    if ($get_survey == false) {
      $data = ['status' =>  false, 'message' =>  "Unknown Error Occurred."];
      $this->return_response(SUCCESS_RESPONSE,  $data);
    } else {
      $data = ['status' =>  true, 'message' =>  "Added successfully.", 'survey' =>  extract_survey($get_survey), 'questions' => (extract_survey_question($get_survey))];
      $this->return_response(SUCCESS_RESPONSE,  $data);
    }
  }

  public function get_unique_survey()
  {
    $payload = $this->check_api();

    $user =  check_user($payload);

    // echo $user;

    $survey_id    = $this->validate_parameter("survey id ", $this->param['survey_id'], INTEGER);
    
    $check = check_if_obj_exits_table3('participation', 'survey_id', $survey_id, 'participant_id', $user);
    $get_survey_and_Q = getQuestionsAndStageWithId($survey_id, $user);
    if ($check == false) {
      $get_survey = getCompleteSurveyWithId($survey_id);
      $data = ['status' =>  true, 'message' =>  "You can participate in this survey.", 'questions' => (extract_survey_question_and_stage($get_survey_and_Q))];
      $this->return_response(SUCCESS_RESPONSE,  $data);
    } else {
      $data = ['status' =>  true, 'message' =>  "Continue participation.", 'questions' => (extract_survey_question_and_stage($get_survey_and_Q))];
      $this->return_response(SUCCESS_RESPONSE,  $data);
    }
  }

  public function get_unique_survey_answer()
  {
    $payload = $this->check_api();

    $user =  check_user($payload);

    // echo $user;

    $survey_id    = $this->validate_parameter("survey id ", $this->param['survey_id'], INTEGER);
    
    $check = check_if_obj_exits_table3('participation', 'survey_id', $survey_id, 'participant_id', $user);

    $get_survey_and_QAA = getUserIncompleteQuestionsAndAnswer($user, $survey_id);
    $data = ['status' =>  true, 'message' =>  "On to the previous question.", 'prev_questions' => $get_survey_and_QAA];
    $this->return_response(SUCCESS_RESPONSE,  $data);
  }

  public function make_withdrawal() {
    $payload = $this->check_api();

    $creator =  check_user($payload);

    $amount             = $this->validate_parameter("amount ", $this->param['amount'], INTEGER);
    $type               = $this->validate_parameter("type ", $this->param['type'], STRING);
    $bank               = $this->validate_parameter("bank ", $this->param['bank'], STRING);
    $acc_name           = $this->validate_parameter("account name ", $this->param['acc_name'], STRING);
    $acc_number         = $this->validate_parameter("account number ", $this->param['acc_number'], INTEGER);
    $wallet_id          = $this->validate_parameter("wallet id ", $this->param['wallet_id'], STRING);

    $bal_exist = check_if_bal_exist($creator, $type);

    if ($bal_exist == true) {
      $valid_amount = check_valid_bal($creator, $type);

      $bank_bal = $valid_amount[0]['amount'];

      if ($bank_bal >= $amount) {
        // echo 'good';
        $withdraw = commence_withdrawal($creator, $type, $amount, $wallet_id, $bank, $acc_name, $acc_number);

        if ($withdraw == true) {
          $data = ['message' => "Your withdrawal request has been queued successfully."];
          $this->return_response(SUCCESS_RESPONSE,  $data);
        } else {
          $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
        }

      } else {
        $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
      }
      
    } else {
      $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
    }
  }

  public function start_survey()
  {  
    $payload = $this->check_api();

    $creator =  check_user($payload);    
        
    $survey_id          = $this->validate_parameter("survey id ", $this->param['survey_id'], INTEGER);
    $question_length    = $this->validate_parameter("question length", $this->param['question_length'], INTEGER);

    $get_survey = getQuestionsAndStageWithId($survey_id, $creator);

    $start  = new Participate();
    $start->survey_id = $survey_id;
    $start->participant_id = $creator;
    $start->question_length = $question_length + 1;
    $start->current_stage = 0;


    if ($start->save()) {

      if ($get_survey == false) {
        $data = ['status' =>  false, 'message' =>  "Unknown Error Occurred."];
        $this->return_response(SUCCESS_RESPONSE,  $data);
      } else {
        $data = ['status' =>  true, 'message' =>  "Added successfully.", 'questions' => (extract_survey_question_and_stage($get_survey))];
        $this->return_response(SUCCESS_RESPONSE,  $data);
      }
    } else {
      $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
    }
    
  }

  public function create_new_survey()
  {

    $payload = $this->check_api();

    $creator =  check_user($payload);
    $survey_title                = $this->validate_parameter("survey title ", $this->param['survey_title'], STRING);
    $survey_language             = $this->validate_parameter("survey language ", $this->param['survey_language'], STRING);
    $introduction_text           = $this->validate_parameter("introduction text ", $this->param['introduction_text'], STRING);
    $duration                    = $this->validate_parameter("Duration ", $this->param['duration'], INTEGER);
    $start_date                  = $this->validate_parameter("Start date ", $this->param['start_date'], STRING);
    $survey_id                   = $this->validate_parameter("survey id ", $this->param['survey_id'], INTEGER, false);

    if ($survey_id == "0") {
      // create new survey
      $create = new Survey();
      $create->survey_creator = $creator;
      $create->survey_title = $survey_title;
      $create->survey_language = $survey_language;
      $create->survey_introduction =  $introduction_text;
      $create->is_survey_published = false;
      $create->has_paid_for_survey = false;
      $create->survey_creation_stage = 1;
      $create->survey_creation_stage = 1;
      $create->start_date = $start_date;
      $create->duration = $duration;
      if ($create->save()) {

        $data = ['message' =>  "Executed successfully.", 'id' =>  $create->id];
        $this->return_response(SUCCESS_RESPONSE,  $data);
      } else {
        $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
      }
    } else {
      // update survey
      $update = Survey::findById($survey_id);
      if (!$update->is_survey_published) {
        if ($creator == $update->survey_creator) {
          $update->survey_title = $survey_title;
          $update->survey_language = $survey_language;
          $update->survey_introduction =  $introduction_text;
          $update->start_date =  $start_date;
          $update->duration =  $duration;
          if ($update->save()) {
            // $data = ['message' =>  "updated successfully.", 'id' =>  $update->id];
            // $this->return_response(SUCCESS_RESPONSE,  $data);

            $get_survey = getIncompleteSurveyWithId($creator);

            if ($get_survey == false) {
              $data = ['status' =>  false];
              $this->return_response(SUCCESS_RESPONSE,  $data);
            } else {
              $data = ['message' =>  "updated successfully.", 'status' =>  true, 'id' =>  $update->id, 'survey' =>  extract_survey($get_survey), 'questions' => (extract_survey_question($get_survey))];
              $this->return_response(SUCCESS_RESPONSE,  $data);
            }
          } else {
            $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
          }
        } else {
          $this->throw_error(FAILED_QUERY,  " You are not the owner of this survey. Thanks. ");
        }
      } else {
        $this->throw_error(FAILED_QUERY,  " Can't edit already published survey. Thanks. ");
      }
    }
  }

  public function add_survey_poster()
  {
    $payload = $this->check_api();

    $creator =  check_user($payload);

    $poster                = $this->validate_parameter("poster", $this->param['poster'], STRING);
    $survey_id        = $this->validate_parameter("survey id", $this->param['survey_id'], INTEGER);

    // update survey
    $update = Survey::findById($survey_id);
    if (!$update->is_survey_published) {
      if ($creator == $update->survey_creator) {
        $update->poster = $poster;
        $update->survey_creation_stage = 3;
        if ($update->save()) {
          // $data = ['message' =>  "updated successfully.", 'id' =>  $update->id];
          // $this->return_response(SUCCESS_RESPONSE,  $data);

          $get_survey = getIncompleteSurveyWithId($creator);

          if ($get_survey == false) {
            $data = ['status' =>  false];
            $this->return_response(SUCCESS_RESPONSE,  $data);
          } else {
            $data = ['message' =>  "updated successfully.", 'status' =>  true, 'id' =>  $update->id, 'survey' =>  extract_survey($get_survey)];
            $this->return_response(SUCCESS_RESPONSE,  $data);
          }
        } else {
          $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
        }
      } else {
        $this->throw_error(FAILED_QUERY,  " You are not the owner of this survey. Thanks. ");
      }
    } else {
      $this->throw_error(FAILED_QUERY,  " Can't edit already published survey. Thanks. ");
    }
  }

  public function get_plans(){
    global $db;
    $get_plan_query = "SELECT DISTINCT `duration`, `amount` FROM `survey_plans`";
    $run_query = $db->query($get_plan_query);
    $get_results = $db->fetchAll($run_query);
    echo json_encode($get_results);
    
  }

  public function publish_survey()
  {
    $payload = $this->check_api();

    $creator =  check_user($payload);

    // $poster                = $this->validate_parameter("poster", $this->param['poster'], STRING);
    $survey_id              = $this->validate_parameter("survey id", $this->param['survey_id'], INTEGER);

    // update survey
    $update = Survey::findById($survey_id);
    if (!$update->is_survey_published) {
      if ($creator == $update->survey_creator) {
        $update->is_survey_published = 1;
        if ($update->save()) {

          $data = ['message' =>  "Congratulation, this survey has been published.", 'status' =>  true];
          $this->return_response(SUCCESS_RESPONSE,  $data);
        } else {
          $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
        }
      } else {
        $this->throw_error(FAILED_QUERY,  " You are not the owner of this survey. Thanks. ");
      }
    } else {
      $this->throw_error(FAILED_QUERY,  " Can't edit already published survey. Thanks. ");
    }
  }

  public function answer_survey()
  {
    $payload = $this->check_api();

    $user =  check_user($payload);
    
    // echo $user;

    $questions_id        = $this->validate_parameter("questions id", $this->param['questions_id'], INTEGER);
    $answer              = $this->validate_parameter("answer", $this->param['answer'], TEXT);
    $question_type       = $this->validate_parameter("question_type", $this->param['question_type'], STRING);
    // $is_complete         = $this->validate_parameter("is_complete", $this->param['is_complete'], STRING);
    $survey_id           = $this->validate_parameter("survey id ", $this->param['survey_id'], INTEGER);
    
    $get_stage1 = Participate::find2ByDynamicFields("survey_id", $survey_id, "participant_id", $user);

    $finalStage = $get_stage1->current_stage + 2;
    $question_length = $get_stage1->question_length;

    // var_dump($finalStage.' hgb '.$question_length);
    $if_exist = check_if_obj_exits_table3('survey_participants', 'survey_question_id', $questions_id, 'survey_participant_id', $user);
    
    if ($finalStage == $question_length) {
      // $send_back =getUserIncompleteQuestionsAndAnswer($user, $survey_id);

      if ($if_exist == true) {
        $multipleInsert = updateMultipleInsert($questions_id, $question_type, $answer, $survey_id, $user);
        
      } else {
        $multipleInsert = finalMultipleInsert($questions_id, $question_type, $answer, $survey_id, $user);
      }
    } else {
      // $send_back =getUserIncompleteQuestionsAndAnswer($user, $survey_id);

      if ($if_exist == true) {
        $multipleInsert = updateMultipleInsert($questions_id, $question_type, $answer, $survey_id, $user);
      } else {
        $multipleInsert = multipleInsert($questions_id, $question_type, $answer, $survey_id, $user);
      }

    }

    // $start  = new Participants();
    // $start->survey_question_id = $questions_id;
    // $start->survey_participant_id = $user;
    // $start->survey_question_answers = $answer;

    if ($multipleInsert == true) {
      $get_stage = Participate::find2ByDynamicFields("survey_id", $survey_id, "participant_id", $user);
      $data = ['status' =>  true, 'message' =>  "Added successfully.", 'question_stage' => $get_stage];
      $this->return_response(SUCCESS_RESPONSE,  $data);
      
    } else {
      $data = ['status' =>  false, 'message' =>  "Could not update participation."];
      $this->return_response(SUCCESS_RESPONSE,  $data);
    }
  }

  // End Survey APIs

  // Event APIs

  public function check_incomplete_event()
  {
    $payload = $this->check_api();

    $creator =  check_user($payload);

    $get_event = getIncompleteEventWithId($creator);

    if ($get_event == false) {
      $data = ['status' =>  false, 'message' =>  "Unknown Error Occurred."];
      $this->return_response(SUCCESS_RESPONSE,  $data);
    } else {
      $data = ['status' =>  true, 'message' =>  "Added successfully.", 'event' =>  extract_event($get_event)];
      $this->return_response(SUCCESS_RESPONSE,  $data);
    }
  }

  public function create_new_event()
  {

    $payload = $this->check_api();

    $creator =  check_user($payload);
    $event_title               = $this->validate_parameter("event title ", $this->param['event_title'], STRING);
    $event_link                = $this->validate_parameter("event link ", $this->param['event_link'], STRING);
    $event_description         = $this->validate_parameter("description text ", $this->param['description_text'], STRING);
    $start_date                = $this->validate_parameter("start date ", $this->param['start_date'], STRING);
    $end_date                  = $this->validate_parameter("end date ", $this->param['end_date'], STRING);
    $event_id                  = $this->validate_parameter("event id ", $this->param['event_id'], INTEGER, false);

    if ($event_id == "0") {
      // create new survey
      $create = new Event();
      $create->event_creator = $creator;
      $create->event_title = $event_title;
      $create->event_link = $event_link;
      $create->event_description = $event_description;
      $create->start_date =  $start_date;
      $create->end_date =  $end_date;
      $create->is_event_published = false;
      $create->has_paid_for_event = false;
      $create->event_creation_stage = 1;
      if ($create->save()) {

        $data = ['message' =>  "Executed successfully.", 'id' =>  $create->id];
        $this->return_response(SUCCESS_RESPONSE,  $data);
      } else {
        $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
      }
    } else {
      // update survey
      $update = Event::findById($event_id);
      if (!$update->is_event_published) {
        if ($creator == $update->event_creator) {
          $update->survey_title = $event_title;
          $update->event_link = $event_link;
          $update->event_description = $event_description;
          $update->start_date =  $start_date;
          $update->end_date =  $end_date;
          if ($update->save()) {
            // $data = ['message' =>  "updated successfully.", 'id' =>  $update->id];
            // $this->return_response(SUCCESS_RESPONSE,  $data);

            $get_event = getIncompleteEventWithId($creator);

            if ($get_event == false) {
              $data = ['status' =>  false];
              $this->return_response(SUCCESS_RESPONSE,  $data);
            } else {
              $data = ['message' =>  "updated successfully.", 'status' =>  true, 'id' =>  $update->id, 'event' =>  extract_event($get_event)];
              $this->return_response(SUCCESS_RESPONSE,  $data);
            }
          } else {
            $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
          }
        } else {
          $this->throw_error(FAILED_QUERY,  " You are not the owner of this survey. Thanks. ");
        }
      } else {
        $this->throw_error(FAILED_QUERY,  " Can't edit already published survey. Thanks. ");
      }
    }
  }

  public function add_event_poster()
  {
    $payload = $this->check_api();

    $creator =  check_user($payload);

    $poster                = $this->validate_parameter("poster", $this->param['poster'], STRING);
    $event_id        = $this->validate_parameter("event id", $this->param['event_id'], INTEGER);

    // update survey
    $update = Event::findById($event_id);
    if (!$update->is_event_published) {
      if ($creator == $update->event_creator) {
        $update->poster = $poster;
        $update->event_creation_stage = 2;
        if ($update->save()) {
          // $data = ['message' =>  "updated successfully.", 'id' =>  $update->id];
          // $this->return_response(SUCCESS_RESPONSE,  $data);

          $get_event = getIncompleteEventWithId($creator);

          if ($get_event == false) {
            $data = ['status' =>  false];
            $this->return_response(SUCCESS_RESPONSE,  $data);
          } else {
            $data = ['message' =>  "updated successfully.", 'status' =>  true, 'id' =>  $update->id, 'event' =>  extract_event($get_event)];
            $this->return_response(SUCCESS_RESPONSE,  $data);
          }
        } else {
          $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
        }
      } else {
        $this->throw_error(FAILED_QUERY,  " You are not the owner of this event. Thanks. ");
      }
    } else {
      $this->throw_error(FAILED_QUERY,  " Can't edit already published event. Thanks. ");
    }
  }

  public function publish_event()
  {
    $payload = $this->check_api();

    $creator =  check_user($payload);

    // $poster                = $this->validate_parameter("poster", $this->param['poster'], STRING);
    $event_id              = $this->validate_parameter("event id", $this->param['event_id'], INTEGER);

    // update survey
    $update = Event::findById($event_id);
    if (!$update->is_event_published) {
      if ($creator == $update->event_creator) {
        $update->is_event_published = 1;
        if ($update->save()) {

          $data = ['message' =>  "Congratulation, this event has been published.", 'status' =>  true];
          $this->return_response(SUCCESS_RESPONSE,  $data);
        } else {
          $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
        }
      } else {
        $this->throw_error(FAILED_QUERY,  " You are not the owner of this event. Thanks. ");
      }
    } else {
      $this->throw_error(FAILED_QUERY,  " Can't edit already published event. Thanks. ");
    }
  }

  public function validate_survey_answer()
  {
    $payload = $this->check_api();

    $creator =  check_user($payload);

    $survey_id              = $this->validate_parameter("survey id", $this->param['survey_id'], INTEGER);
    $user_id                = $this->validate_parameter("user id", $this->param['user_id'], INTEGER);
    $amount                 = $this->validate_parameter("amount", $this->param['amount'], INTEGER);

    $update = getBankId($creator);

    if ($update == true) {
      // $surveyee = $update[0]['id'];
      $result = validateAndUpdate($creator, $survey_id, $user_id, $amount);
    } else {
      $result = validateAndInsert($creator, $survey_id, $user_id, $amount);
    }

    if ($result == true) {
      $data = ['message' =>  "Congratulation, you have validated this survey.", 'status' =>  true];
      $this->return_response(SUCCESS_RESPONSE,  $data);
    } else {
      $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
    }
    
  }

  // End Event APIs

  // Fetch for UI

  public function fetch_events()
  {
    $payload = $this->check_api();

    $creator =  check_user($payload);

    // $brand_id =           $this->validate_parameter("id", $this->param['id'], INTEGER);
    $findAll = getExEventById($creator);
    // print_r($findAll);
    $data = json_encode($findAll);
    $this->return_response(SUCCESS_RESPONSE,  $findAll);
  }

  public function fetch_ext_surveys()
  {
    $payload = $this->check_api();

    $creator =  check_user($payload);

    // $brand_id =           $this->validate_parameter("id", $this->param['id'], INTEGER);
    $findAll = getExSurveyById($creator);
    // print_r($findAll);
    $data = json_encode($findAll);
    $this->return_response(SUCCESS_RESPONSE,  $findAll);
  }

  public function fetch_surveys()
  {
    $payload = $this->check_api();

    $creator =  check_user($payload);

    // $brand_id =           $this->validate_parameter("id", $this->param['id'], INTEGER);
    $findAll = getMySurvey($creator);
    // print_r($findAll);
    $data = json_encode($findAll);
    $this->return_response(SUCCESS_RESPONSE,  $findAll);
  }

  public function fetch_all_surveys()
  {
    $payload = $this->check_api();

    $creator =  check_user($payload);

    // $brand_id =           $this->validate_parameter("id", $this->param['id'], INTEGER);
    $findAll = getAllSurvey();
    // print_r($findAll);
    $data = json_encode($findAll);
    $this->return_response(SUCCESS_RESPONSE,  $findAll);
  }

  public function add_survey_plan()
  {  
        
    $update_plan        = $this->validate_parameter("update plan ", $this->param['update_plan'], STRING);
    $survey_plan        = $this->validate_parameter("survey plan ", $this->param['survey_plan'], STRING);
    $survey_duration    = $this->validate_parameter("survey duration ", $this->param['survey_duration'], STRING);
    $plan_amount        = $this->validate_parameter("plan amount", $this->param['plan_amount'], INTEGER);

    if ($update_plan === 'newplan') {
      $start  = new Plans();
      // $start = Plans::findByDynamicFields("name", $update_plan);

      $start->name        =  $survey_plan;
      $start->amount      =  $plan_amount;
      $start->duration    =  $survey_duration;
      // $start->updatedAt   =  $date;

      if ($start->save()) {
        $data = ['status' =>  true, 'message' =>  "You have successfully added a new plan."];
        $this->return_response(SUCCESS_RESPONSE,  $data);
      } else {
        $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
      }
    } else {
      $check = check_if_obj_exits_table("survey_plans", "name", $update_plan);

      if ($check == true) {

        $post_time = time();
        $date = date('Y-m-d H:i:s', $post_time);
        
        $start  = new Plans();
        $start = Plans::findByDynamicFields("name", $update_plan);

        $start->name        =  $survey_plan;
        $start->amount      =  $plan_amount;
        $start->duration    =  $survey_duration;
        $start->updatedAt   =  $date;

        if ($start->save()) {
          $data = ['status' =>  true, 'message' =>  "Updated successfully."];
          $this->return_response(SUCCESS_RESPONSE,  $data);
        } else {
          $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
        }
      } else {
        $this->throw_error(FAILED_QUERY,  "Sorry, you can only update the available plans.");
      }
    }
    

    
  }

  public function fetch_survey_plans()
  {
    $findAll = Plans::findAll();

    // $data  = json_encode($driver);
    // echo $findAll;
    $data = json_encode($findAll);
    $this->return_response(SUCCESS_RESPONSE,  $data);
  
  }

  public function delete_survey_plan()
  {

    $id =           $this->validate_parameter("id", $this->param['id'], INTEGER);
    // $table =        $this->validate_parameter("table", $this->param['table'], STRING);

    $result = deleteById('survey_plans', $id);
    
    if($result == true){
    
      $data = ['message' => "Delete was successful!"];
      $this->return_response(SUCCESS_RESPONSE,  $data);
    }else{
      $this->throw_error(FAILED_QUERY, "Unknown error occurred.");
    }
  }

  // Registration and login APIs

  public function change_password()
  {

    $code                = $this->validate_parameter("code", $this->param['code'], STRING);
    $new_password        = $this->validate_parameter("new_password", $this->param['new_password'], STRING);
    $confirm_password    = $this->validate_parameter("confirm_password", $this->param['confirm_password'], STRING);

    if (check_if_obj_exits_table("user_reg", "email_auth_code", $code)) {
      if ($new_password == $confirm_password) {
        $customer = Signup::findByDynamicFields("email_auth_code", $code);

        $customer->password   =  sha1($new_password);

        if ($customer->save()) {
          // $msg =  "<br><p>Hi <b>" . $customer->first_name . " , " . $customer->last_name . "</b></p>
          //   <p>The password for your sonergy account (" . $customer->email . ") has been successfully reset.\r\n</p>
          //   <p>If you didn’t make this change or if you believe an unauthorized person has accessed your account,
          //    click <a href=\"" . SITE_DOMAIN_NAME . "/password/reset\">here</a> to reset your password immediately.";
          // $html_email =  build_email($msg);

          // send_email(PASSWORD_RESET, $customer->email, $html_email);

          $data = ['message' =>  "A password changed successfully."];
          $this->return_response(SUCCESS_RESPONSE,  $data);
        } else {
          $this->throw_error(FAILED_QUERY,  " Unknown error has occurred. ");
        }
      } else {

        $this->throw_error(INVALID_USER_PASS,  "Password does not match");
      }
    } else {
      $this->throw_error(INVALID_USER_PASS,  "Authorization denied because you supplied invalid credentials.");
    }
  }

  public function change_username()
  {

    $oldUsername                = $this->validate_parameter("oldUsername", $this->param['oldUsername'], STRING);
    $newUsername        = $this->validate_parameter("newUsername", $this->param['newUsername'], STRING);

    if (check_if_obj_exits_table("user_reg", "user_name", $oldUsername)) {
        $customer = Signup::findByDynamicFields("user_name", $oldUsername);

        $customer->user_name   =  $newUsername;

        if ($customer->save()) {

          $data = ['message' =>  "Username has been updated successfully."];
          $this->return_response(SUCCESS_RESPONSE,  $data);
        } else {
          $this->throw_error(FAILED_QUERY,  " Unknown error has occurred. ");
        }
      
    } else {
      $this->throw_error(INVALID_USER_PASS,  "Authorization denied because you supplied invalid credentials.");
    }
  }

  public function reset_password()
  {
    $email = $this->validate_parameter("email", $this->param['email'], STRING);

    if (check_if_obj_exits_table("user_reg", "email", $email)) {

      $token = generate_token_with_time($email);
      $email_generated_code  =  sha1($token);
      $customer = Signup::findByDynamicFields("email", $email);

      $customer->email_auth_code =  $email_generated_code;

      if ($customer->save()) {
        $msg =  "<br><p>Hi <b>" . $customer->user_name . "</b></p>
          <p>We recently received your request to reset your password.\r\n</p>
          <p>If you did not make this request, you can safely ignore this email. & it does not mean your account is in danger.
          </p>

          <p>But if you are the one that made the request, click on below link to reset your password

            <a href=\"" . SITE_DOMAIN_NAME . "/password/reset/" . $email_generated_code . "\">" . SITE_DOMAIN_NAME . "/password/reset/" . $email_generated_code . " </a>
          <p>If the above link can not click. Please copy the link to the browser's address bar to open it.</p>";

        $html_email =  build_email($msg);

        $weed = send_email(PASSWORD_RESET, $email, $html_email);
        

        $data = ['message' =>  "A password reset link has been sent to your email address check your email for further instructions."];
        $this->return_response(SUCCESS_RESPONSE,  $data);
      } else {
        $this->throw_error(FAILED_QUERY,  " Unknown error has occurred. ");
      }
    } else {
      $this->throw_error(INVALID_USER_PASS, $email . " does not exist. check & try again. ");
    }
  }

  //register user and send activation email
  public function register_user()
  {


    $referal =    $this->validate_parameter("referal", $this->param['referal'], STRING);
    $user_name =  $this->validate_parameter("username", $this->param['user_name'], STRING);
    $email =      $this->validate_parameter("email", $this->param['email'], STRING);
    $password =   $this->validate_parameter("password", $this->param['password'], STRING);

    $token = generate_token_with_time($email);
    $email_generated_code  =  sha1($token);
    $customer = new Signup();

    $customer->referal      =  $referal;
    $customer->email      =  $email;
    $customer->user_name =  $user_name;
    $customer->password   =  sha1($password);
    $customer->email_auth_code = $email_generated_code;

    if (check_if_obj_exits_table("user_reg", "email", $customer->email) == false) {


      if ($customer->save()) {
        $msg =  "<br><p>Hi <b>" . $customer->user_name . "</b></p>
                        <p>Thank you for choosing " . SITE_DOMAIN_NAME . "!\r\n</p>
                        <p>Please click on the link below to complete the registration:</p>
                        
                          <a href=\"" . SITE_DOMAIN_NAME . "/activate/" . $email_generated_code . "\">" . SITE_DOMAIN_NAME . "/activate/" . $email_generated_code . " </a>
                        <p>If the above link can not click. Please copy the link to the browser's address bar to open it.</p>";

        $html_email =  build_email($msg);

        send_email(ACCOUNT_VERIFICATION, $customer->email, $html_email);

        $data = ['message' => "Your registration was successful!"];
        $this->return_response(SUCCESS_RESPONSE,  $data);
      } else {
        $this->throw_error(FAILED_QUERY, "Unknown error occurred.");
      }
    } else {
      $this->throw_error(FAILED_QUERY, "This email has already been asigned to a user.");
    }
  }

  public function edit_profile()
  {

    $code =     $this->validate_parameter("code", $this->param['code'], STRING);
    $imgPath =   $this->validate_parameter("imgPath", $this->param['imgPath'], STRING);
    

    $findAll = updateUserProfile($code, $imgPath);

    if ($findAll == true) {

      $data = ['message' => "Your have successfully edited your information."];
      $this->return_response(SUCCESS_RESPONSE,  $data);
    } else {
      $this->throw_error(FAILED_QUERY, "Unknown error occurred.");
    }
  }

  public function add_document()
  {

    $code =     $this->validate_parameter("code", $this->param['code'], STRING);
    $docPath =   $this->validate_parameter("docPath", $this->param['docPath'], STRING);
    

    $findAll = addUserDocument($code, $docPath);

    if ($findAll == true) {

      $data = ['message' => "Your have successfully added your document."];
      $this->return_response(SUCCESS_RESPONSE,  $data);
    } else {
      $this->throw_error(FAILED_QUERY, "Unknown error occurred.");
    }
  }

  public function resend_activation()
  {
    $email =      $this->validate_parameter("email", $this->param['email'], STRING);
    $password =   $this->validate_parameter("password", $this->param['password'], STRING);

    $check = getEmailCode("user_reg", "email", $email, "password", $password);

    if ($check) {
      $user_name = $check[0]['user_name'];
      $email_auth_code = $check[0]['email_auth_code'];

      $msg =  "<br><p>Hi <b>" . $user_name . "</b></p>
                      <p>Thank you for choosing " . SITE_DOMAIN_NAME . "!\r\n</p>
                      <p>Please click on the link below to complete the registration:</p>
                      
                        <a href=\"" . SITE_DOMAIN_NAME . "/activate/" . $email_auth_code . "\">" . SITE_DOMAIN_NAME . "/activate/" . $email_auth_code . " </a>
                      <p>If the above link can not click. Please copy the link to the browser's address bar to open it.</p>";

      $html_email =  build_email($msg);

      send_email(ACCOUNT_VERIFICATION, $email, $html_email);

      $data = ['message' => "Activation email has been sent to your email address."];
      $this->return_response(SUCCESS_RESPONSE,  $data);
    } else {
      $this->throw_error(FAILED_QUERY, "Unknown error occurred.");
    }
  }
  // Payment API
  public function payment()
  {

    $token = $this->check_api();

    $user_id = check_user($token);


    // print_r($user_id);

    $r_id =                $this->validate_parameter("user_id", $user_id, INTEGER);
    $email =               $this->validate_parameter("email", $this->param['email'], STRING);
    $paymenttype =         $this->validate_parameter("paymenttype", $this->param['paymenttype'], STRING);
    $cause_id =            $this->validate_parameter("cause_id", $this->param['cause_id'], STRING);
    $message =             $this->validate_parameter("message", $this->param['message'], STRING);
    $reference =           $this->validate_parameter("reference", $this->param['reference'], STRING);
    $response =            $this->validate_parameter("response", $this->param['response'], STRING);


    $trans =               $this->validate_parameter("trans", $this->param['trans'], STRING);
    $trxref =              $this->validate_parameter("trxref", $this->param['trxref'], STRING);
    $status =              $this->validate_parameter("status", $this->param['status'], STRING);
    $amount =              $this->validate_parameter("amount", $this->param['amount'], INTEGER);
    $date =                $this->validate_parameter("date", $this->param['date'], STRING);


    $pay1 = new Payment();
    $pay1->user_id = $r_id;
    $pay1->type = $paymenttype;
    $pay1->email = $email;
    $pay1->message = $message;
    $pay1->reference = $reference;
    $pay1->response = $response;
    $pay1->trans = $trans;
    $pay1->trxref = $trxref;
    $pay1->status = $status;
    $pay1->amount = $amount;
    $pay1->date = $date;


    if ($pay1->save()) {
      if ($paymenttype == "referal") {
        $update = Signup::findById($user_id);
        $update->has_paid = 1;
        if ($update->save()) {
          $data = ['message' => "Your referal payment was successful!"];
          $this->return_response(SUCCESS_RESPONSE,  $data);
        } else {
          $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
        }
      } else if ($paymenttype == "event") {
        $update = Event::findById($cause_id);
        if ($user_id == $update->event_creator) {
          $update->has_paid_for_event = 1;
          if ($update->save()) {
            $data = ['message' => "Your payment for this event was successful!"];
            $this->return_response(SUCCESS_RESPONSE,  $data);
          } else {
            $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
          }
        } else {
          $this->throw_error(FAILED_QUERY,  " You are not the owner of this event. Thanks. ");
        }
      } else if ($paymenttype == "SNGY") {
        $update = Survey::findById($cause_id);
        if ($user_id == $update->survey_creator) {
          $update->has_paid_for_survey = 1;
          if ($update->save()) {
            $data = ['message' => "Your payment for this survey was successful!"];
            $this->return_response(SUCCESS_RESPONSE,  $data);
          } else {
            $this->throw_error(FAILED_QUERY,  " was unable to execute. Try again later! ");
          }
        } else {
          $this->throw_error(FAILED_QUERY,  " You are not the owner of this survey. Thanks. ");
        }
      }
    } else {
      $this->throw_error(FAILED_QUERY, "Unknown error occurred.");
    }
  }

  public function count_user_referal()
  {
    $email = $this->validate_parameter("email", $this->param['email'], STRING);
    //  echo $user_id;

    $referals = count_referal($email);

    //  print_r($unique_drivers_num);

    $data = json_encode($referals);
    $this->return_response(SUCCESS_RESPONSE,  $data);
  }

  public function count_user_stat()
  {
    $payload = $this->check_api();

    $creator =  check_user($payload);

    $email = $this->validate_parameter("email", $this->param['email'], STRING);
    //  echo $user_id;

    $count = count_stat($email, $creator);

    //  print_r($count);

    $data = json_encode($count);
    $this->return_response(SUCCESS_RESPONSE,  $data);
  }

  public function count_admin_stat()
  {
    // $payload = $this->check_api();
    // $creator =  check_user($payload);
    $count = countAdminStat();
    //  print_r($count);

    $data = json_encode($count);
    $this->return_response(SUCCESS_RESPONSE,  $data);
  }
  
  public function total_sngy()
  {

    $payload = $this->check_api();

    $creator =  check_user($payload);

    $sum = sum_sngy($creator);

    $data = json_encode($sum);
    $this->return_response(SUCCESS_RESPONSE,  $data);
  }

  public function fetch_approved_users()
  {

    $payload = $this->check_api();

    $creator =  check_user($payload);

    $sum = getApprovedUsers();

    $data = json_encode($sum);
    $this->return_response(SUCCESS_RESPONSE,  $sum);
  }

  public function fetch_user_survey()
  {
    $payload = $this->check_api();
    $creator =  check_user($payload);
    $user_id = $this->validate_parameter("user_id", $this->param['user_id'], INTEGER);

    $sum = getUserSurvey($user_id, $creator);

    $data = json_encode($sum);
    $this->return_response(SUCCESS_RESPONSE,  $sum);
  }

  public function fetch_amount_spent()
  {

    $payload = $this->check_api();

    $creator =  check_user($payload);

    $sum = amount_spent($creator);

    $data = json_encode($sum);
    $this->return_response(SUCCESS_RESPONSE,  $data);
  }

  public function fetch_user_answers()
  {

    // $payload = $this->check_api();

    // $creator =  check_user($payload);

    $user_id = $this->validate_parameter("user_id", $this->param['user_id'], INTEGER);
    $survey_id = $this->validate_parameter("survey_id", $this->param['survey_id'], INTEGER);


    $sum = getUserQuestionsAndAnswer($user_id, $survey_id);

    $data = json_encode($sum);
    $this->return_response(SUCCESS_RESPONSE,  $sum);
  }

  public function fetch_my_answers()
  {

    // $payload = $this->check_api();

    // $creator =  check_user($payload);

    // $user_id = $this->validate_parameter("user_id", $this->param['user_id'], INTEGER);
    $survey_id = $this->validate_parameter("survey_id", $this->param['survey_id'], INTEGER);


    $sum = getMyQuestionsAndAnswer($survey_id);

    $data = json_encode($sum);
    $this->return_response(SUCCESS_RESPONSE,  $sum);
  }

  public function fetch_my_survey_users()
  {

    // $payload = $this->check_api();

    // $creator =  check_user($payload);

    // $user_id = $this->validate_parameter("user_id", $this->param['user_id'], INTEGER);
    $survey_id = $this->validate_parameter("survey_id", $this->param['survey_id'], INTEGER);


    $sum = getMySurveyUsers($survey_id);

    $data = json_encode($sum);
    $this->return_response(SUCCESS_RESPONSE,  $sum);
  }

  public function fetch_unique_transaction()
  {

    $payload = $this->check_api();

    $creator =  check_user($payload);

    $type = $this->validate_parameter("type", $this->param['type'], STRING);

    $transaction = fetch_transactions($creator, $type);

    // print_r($transaction);

    $data = json_encode($transaction);
    $this->return_response(SUCCESS_RESPONSE,  $transaction);
  }

  public function admin_referal()
  {
    $email = $this->validate_parameter("email", $this->param['email'], STRING);
    //  echo $user_id;

    $referals = fetch_referal($email);

    //  print_r($unique_drivers_num);

    $data = json_encode($referals);
    $this->return_response(SUCCESS_RESPONSE,  $referals);
  }

  public function check_api()
  {
    try {
      $token = $this->get_bearer_token();
      $payload = JWT::decode($token, SECRETE_KEY, ['HS256']);
      return $payload;
      //print_r($payload);
    } catch (\Throwable $th) {
      $this->throw_error(ACCESS_TOKEN_ERRORS, $th->getMessage());
    }

    // print_r($_SERVER);
  }
}
