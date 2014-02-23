<?php

class LogController extends BaseController {

	/*
	|--------------------------------------------------------------------------
	| Log Entry Controller
	|--------------------------------------------------------------------------
	|
	| This controller handles log entry requests such as adding new entries and
	| modifying previous entries.
	|
	*/

	/*
	* getValidator: generate the validator that will be used by addEntry() and editEntry()
	*/
	private function validateInput()
	{
		/*
		*  Returns true if the date-time value of this field is greater than the
		*  value of the provided attribute name.
		*
		*  Format:
		*    after_start:startAttributeName
		*
		*/
		Validator::extend('after_start', function($attribute, $value, $parameters)
		{
			$start = new DateTime(Input::get($parameters[0]));
			$end = new DateTime($value);

			return $end > $start;
		});
		
		/*
		* A valid name consists of print characters and spaces, not including slashes (\ nor /).
		* A valid name is also one that is at least of length 1 when not counting white space.
		*/
		Validator::extend('validName', function($attribute, $value, $parameters)
		{
			$validchars = preg_match('/[a-zA-z0-9-_ \.\+\*\?&\]\[\}\{\|\(\)\$%\^#!@]+/', $value);
			if($validchars === false)
				return false;
			return count(str_replace(' ','',$value)) > 0;
		});
	
		// validate
		$validator = Validator::make(Input::all(), array(
			'entryname' => 'required|validName',
			'startDateTime' => 'required|date',
			'endDateTime' => 'required|date|after_start:startDateTime',
			'category' => 'alpha_dash'
			),
			array('after_start' => 'End date-time must be after start date-time.')
		);

		return $validator;
	}

	public function addEntry()
	{	
		// user must be logged in!
		if(!Auth::check()){
			return;
		}
		
		$validator = validateInput(); // validate input from Input::all()
		
		if ($validator->passes()) {
			// validation has passed, save user in DB
			
			// create new LogEntry
			$entry = new LogEntry;
			$entry->startDateTime = Input::get('startDateTime');
			$entry->endDateTime = Input::get('endDateTime');
			
			// calculate duration
			
			$start = new DateTime($entry->startDateTime);
			$end = new DateTime($entry->endDateTime);
			
			$interval = $start->diff($end);
			$d = intval($interval->format('%a'));
			$h = intval($interval->format('%h'));
			$m = intval($interval->format('%i'));
			$entry->duration = ((($d * 24) + $h) * 60) + $m;
			
			// save to DB
			$entry->notes = '';
			$entry->UID = Auth::user()->id;
			$entry->save();

			return Redirect::to('log/view');
		} else {
			// validation has failed, display error messages
			return Redirect::to('log/add')->withErrors($validator);
		}
	}
}