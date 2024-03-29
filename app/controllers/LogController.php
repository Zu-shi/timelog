<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;

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
			try{
				$start = new DateTime(Input::get($parameters[0]));
				$end = new DateTime($value);
			}catch(Exception $e){
				return false;
			}

			return $end > $start;
		});
		
		/*
		* A valid name consists of print characters and spaces, not including slashes (\ nor /).
		* A valid name is also one that is at least of length 1 when not counting white space.
		*/
		Validator::extend('valid_name', function($attribute, $name, $parameters)
		{
			return Utils::validateName($name);
		});
		
		/* 
		* A valid color is a hexadecimal string of six characters without the # sign, no more,
		* no less.
		*/
		Validator::extend('valid_color', function($attribute, $color, $parameters)
		{
			return Utils::validateColor($color);
		});

		/* 
		* A valid rating is a char between 1 and 3, no more, no less.
		*/
		Validator::extend('valid_rating', function($attribute, $rating, $parameters)
		{
			return Utils::validateRating($rating);
		});
		/*
		* A valid CID is one that corresponds to a Category that the user owns. It's also valid to
		* have no selected CID (value of 0 or "").
		*/
		Validator::extend('valid_cid', function($attribute, $cid, $parameters)
		{
			return Utils::validateCID($cid);
		});

		// validate
		$validator = Validator::make(Input::all(), array(
				'CID' => 'integer|valid_cid',
				'newcat' => 'valid_name',
				'startDateTime' => 'required|date',
				'endDateTime' => 'required|date|after_start:startDateTime',
				'color' => 'valid_color',
				'rating' => 'valid_rating'
			),
			array(
				'after_start' => 'End date-time must be after start date-time.',
				'valid_cid' => 'Please select a valid category.',
				'valid_name' => 'Your new category name cannot have slash characters (i.e. \'/\' and \'\\\') and must be at least 1 non-white-space character long.',
				'valid_color' => 'You have used an invalid color scheme',
				'valid_rating' => 'Your rating is invalid... Whoa, How did you manage to do that?'
			)
		);

		return $validator;
	}

	public function saveEntry($id = null)
	{
		$save_entry_result = array("success" => 0, "errors" => array(), "log" => null);
		
		if(!Auth::check()){
			$save_entry_result["errors"] = array('You need to be logged in to perform this operation.');
			return $save_entry_result;
		}

		$entry = null;

		if($id == null){
			// create new LogEntry
			$entry = new LogEntry;
		} else {
			// load existing LogEntry
			try{
				$entry = LogEntry::where('UID', '=', Auth::user()->id)->where('LID', '=', $id)->findOrFail($id);
			}catch(ModelNotFoundException $e){
				$save_entry_result["errors"] = array("The specified log entry does not exist.");
				return $save_entry_result;
			}
		}
		
		$validator = $this->validateInput(); // validate input from Input::all()
		
		if ($validator->passes()) {
			// validation has passed, save data in DB

			$cid = intval(Input::get('CID'));
			if($cid == 0)
				$cid = NULL;

			$colorstr = Input::get('color');
			//$rating = Input::get('rating');
			$newcatstr = trim(Input::get('newcat'));

			if(!empty($newcatstr)){
				try{
					$existingcat = LogCategory::where('UID', '=', Auth::user()->id)->where('PID', '=', $cid)->where('name', '=', $newcatstr)->firstOrFail();
					$cid = $existingcat->CID;
				}catch(ModelNotFoundException $e){
					$newcat = new LogCategory;
					$newcat->UID = Auth::user()->id;
					$newcat->PID = $cid;
					$newcat->name = $newcatstr;
					$newcat->color = $colorstr;
					$newcat->isTask = 0;
					$newcat->isCompleted = 0;
					$newcat->rating = 0;
					$newcat->deadline = NULL;
					$newcat->save();
					$cid = $newcat->CID;
				}
			}

			// Default to uncategorized if no CID was provided.
			if($cid == NULL){
				$cid = LogCategory::where('UID', '=', Auth::user()->id)->where('PID', '=', NULL)->where('name', '=', "Uncategorized")->pluck("CID");
			}

			$entry->CID = $cid;
			
			// calculate duration
			
			$start = new DateTime(Input::get('startDateTime'));
			$end = new DateTime(Input::get('endDateTime'));

			$entry->startDateTime = $start->format('Y-m-d H:i:s');
			$entry->endDateTime = $end->format('Y-m-d H:i:s');
			
			$interval = $start->diff($end);
			$d = intval($interval->format('%a'));
			$h = intval($interval->format('%h'));
			$m = intval($interval->format('%i'));

			$entry->duration = ((($d * 24) + $h) * 60) + $m;
			
			// save to DB
			$entry->notes = Input::get('notes');
			$entry->UID = Auth::user()->id;
			$entry->save();

			$LID = $entry->LID;
			$entry->category = LogCategory::where('UID', '=', Auth::user()->id)->where('CID', '=', $cid)->pluck('name');
			$entry->color = LogCategory::where('UID', '=', Auth::user()->id)->where('CID', '=', $cid)->pluck('color');

			return array("success" => 1, "errors" => array(), "log" => $entry);

		} else {
			return array("success" => 0, "errors" => $validator, "log" => null);
		}
	}

	public function saveEntryFromAddPage($id = null){
		
		$val = $this->saveEntry($id); // returns [LID, $validator], where LID is NULL on error

		if($val == NULL)
			return Response::make('Not Found', 404);
		if($val["success"] === 1){
			return Redirect::to('log/view');
		} else if($id == null) {
			// validation has failed, display error messages
			Input::flash();
			return Redirect::to('log/add')->withErrors($val["errors"]);
		} else {
			// validation has failed, display error messages
			Input::flash();
			return Redirect::to('log/edit/'.$id)->withErrors($val["errors"]);
		}
	}

	//This function bypasses returning a page upon successful submission to optimize for speed.
	public function saveEntryFromCalendar($id = null){
		
		$save_entry_result = $this->saveEntry($id);
		
		if($save_entry_result["success"] == 1){
			return $save_entry_result["log"];
		} else{
			Input::flash();
			return $save_entry_result["errors"]->messages();
		}
	}

	public function editEntry($id, $modal = false)
	{	
		// user must be logged in!
		if(!Auth::check()){
			return Response::make('Not Found', 404);
		}

		try{
			$entry = LogEntry::where('UID', '=', Auth::user()->id)->findOrFail($id);
		}catch(ModelNotFoundException $e){
			return Response::make('Not Found', 404);
		}

		if($modal === false)
			return View::make('entryform')->with(array('editThis' => $entry, 'categories' => Utils::getSelectCats()));
		else
			return View::make('entryform_modal')->with(array('editThis' => $entry, 'categories' => Utils::getSelectCats()));
	}

	public function getLogAdd($modal = false){
		$sendToForm = array('active'=>'addlog', 'categories' => Utils::getSelectCats());
		return Auth::check() != null ? ($modal == false ? View::make('entryform')->with($sendToForm) : View::make('entryform_modal')->with($sendToForm)) : Redirect::to('login');
	}


	/*
	* validateCategory: generate the validator that will be used by addCategory() and editEntry()
	*/
	private function validateCategory()
	{
		/*
		* A valid name consists of print characters and spaces, not including slashes (\ nor /).
		* A valid name is also one that is at least of length 1 when not counting white space.
		*/
		Validator::extend('validName', function($attribute, $value, $parameters)
		{
			return Utils::validateName($value);
		});

		// validate
		$validator = Validator::make(Input::all(), array(
			'categoryName' => 'required|validName',
			'taskDeadline' => 'date'
			)
		);

		return $validator;
	}

	public function saveCategory($id = null, $stopRedirectToView = false) {
		
		if(!Auth::check()){
			return Response::make('Must be logged int to save a category', 404);
		}

		if($id == null){
			// create new category entry
			$catEntry = new logCategory;
		}

		$validator = $this->validateCategory(); // validate input from Input::all()
		
		if ($validator->passes()) {
			// validation has passed, save category in DB
			$catEntry->UID = Auth::user()->id;
			$catEntry->name = Input::get('categoryName');
			$testName = DB::select("select * from log_category c where c.name ='" . "$catEntry->name' AND c.uid = '" . "$catEntry->UID'");
			if ($testName != NULL){
				Input::flash();
				return Redirect::to('log/addCategory')->with('message','Category name already taken')->withErrors($validator);
			}
			
			//Setup PID
			$superCategory = Input::get('superCategory');
			$catEntry->PID = DB::table('log_category')->where('CID',$superCategory)->where('UID',Auth::user()->id)->pluck('CID');
			//die(print_r($_POST));

			$catEntry->color = Input::get('color');
			$catEntry->isTask = Input::get('isTask');

			$catEntry->isCompleted = Input::get('isCompleted');
			if($catEntry->isCompleted == NULL){
				$catEntry->isCompleted = 0;
			}
			
			$duedate = Input::get('hasDuedate');

			if($catEntry->isTask == '0'){
				$catEntry->isTask = 0;
				$catEntry->isCompleted = 0;
				$catEntry->rating = 0;
			}else {
				if ($catEntry->isCompleted == 0) {
					$catEntry->rating = 0;
				}else {
					$catEntry->rating = Input::get('starRating');
				}
				
				if ($duedate == 0) {
					$catEntry->deadline = NULL;
				}else {
					$catEntry->deadline = Input::get('dueDateTime');
				}
			}

			//save category to database
			$catEntry->save();
			if( !$stopRedirectToView ){ return Redirect::to('log/view'); }
		} else if($id == null) {
			// validation has failed, display error messages
			Input::flash();
			if(Input::get('isTask') == '0'){
				return Redirect::to('log/addCategory')->withErrors($validator);
			}else{
				return Redirect::to('log/addTask')->withErrors($validator);
			}
		}else{
			// validation has failed, display error messages
			Input::flash();
			return Redirect::to('log/edit/'.$id)->withErrors($validator);
		}

	}

	public function editCat($catID, $modal = false){
		// user must be logged in!
		if(!Auth::check()){
			return Response::make('Not Found', 404);
		}

		try{
			$entry = LogCategory::where('UID', '=', Auth::user()->id)->where('CID', '=', $catID)->findOrFail($catID);

		}catch(ModelNotFoundException $e){
			return Response::make('Not Found', 404);
		}

		if($modal === false){
			//return "HERE1";
			return View::make('addCategory')->with('editThis', $entry);
		}
		else{
			return View::make('editCategory_modal')->with('editThis', $entry);
		}

	}

	public function editTask($catID, $modal = false){
		// user must be logged in!
		if(!Auth::check()){
			return Response::make('Not Found', 404);
		}

		try{
			$entry = LogCategory::where('UID', '=', Auth::user()->id)->where('CID', '=', $catID)->findOrFail($catID);

		}catch(ModelNotFoundException $e){
			return Response::make('Not Found', 404);
		}

		if($modal === false){
			//return "HERE1";
			return View::make('addTask')->with('editThis', $entry);
		}
		else{
			return View::make('editTask_modal')->with('editThis', $entry);
		}

	}	

		/* The checkCatCycle Function should be called whenever any category changes it's
	   parent category (e.g., changing a categorie's possible subcategory). This will recursively
	   check to make sure that a category is not it's own subcategory. $inputCatID is the current category of
	   the current category ID you're working with, and subCatId is the category ID of the new parent category the user
	   wants the category to be a part of. This returns 1 if there's a cycle and 0 if there is not */
	public function checkCatCycle($inputCatID, $newParentCatID) {


		if($inputCatID == $newParentCatID){
			return 1;
		}

		$subCategoryPID = DB::table('log_category')->where('CID',$newParentCatID)->where('UID',Auth::user()->id)->pluck('PID');
		
		if ($subCategoryPID == NULL){
			return "WAIT HERE";
			return 0;
		}

		if($subCategoryPID == $inputCatID){
			return 1;
		}

		else {
			return "CALLED HERE";
			return $this->checkCatCycle($inputCatID, $subCategoryPID);
		}
	}

	public function updateCategory($catID) {

		if (!Auth::check()){
			return Response::make('Not Found', 404);
		}

		if ($catID == null){
			$entry = new LogCategory;
		}
		else{
			try{
				$entry = LogCategory::where('CID', '=', $catID)->findOrFail($catID);
			}catch(ModelNotFoundException $e){
				return "Response::make('Not Found', 404)";
			}			
		}

		$validator = $this->validateCategory();

		if($validator->passes()){

			$entry->UID= Auth::user()->id;
			$entry->name = Input::get('categoryName');

			$superCategory = Input::get('superCategory');

			if ($superCategory == null){
				$entry->PID = null;
			}

			else{

				$entry->PID = Input::get('superCategory');
				if ($entry->PID == null)
					return "Response::make('parent category name doesn't exist', 404)";
				$returnValue = $this->checkCatCycle($entry->CID, $entry->PID);
				if ($returnValue == 1){
					return "Response::make('Subcategory Cycle', 404)";
				}

			}


			$entry->deadline = Input::get('taskDeadline');

			if ($entry->deadline == NULL)
				$entry->isTask = 0;
			else
				$entry->isTask = 1;

			if($entry->PID == 0){
				$entry->PID = null;
			}

			if(Input::get('color') != null){
				$entry->color = Input::get('color');
			}

			$entry->isTask = Input::get('isTask');
			$star = Input::get('starRating');

			if(!$entry->isTask == 1){
				$entry->isTask = 0;
				$entry->isCompleted = 0;
				$entry->rating = 0;
			}
			else {
				$entry->rating= $star;
				if(Input::get('dueDateTime') == "Invalid date")
					$entry->deadline = $entry->deadline;
				else
					$entry->deadline = Input::get('dueDateTime');
				if ($star == 0) {
					$entry->isCompleted = 0;
				}
				else {
					$entry->isCompleted = 1;
				}
				if( Input::get('isCompleted') == 0){
					$entry->rating = 0;
					$entry->isCompleted = 0;
				}
			}

			$updateThis = LogCategory::find($entry->CID);

			
			$updateThis->CID = $entry->CID;
			$updateThis->PID = $entry->PID;
			$updateThis->name = $entry->name;
			$updateThis->color = $entry->color;
			$updateThis->isTask = $entry->isTask;
			$updateThis->deadline = $entry->deadline;
			$updateThis->isCompleted = $entry->isCompleted;
			$updateThis->rating = $entry->rating;
			$updateThis->save();
			
			return Redirect::to('log/viewCategory');


		} 
		if($catID == null) {
			// validation has failed, display error messages
			Input::flash();
			return Redirect::to('log/viewCategory')->withErrors($validator);
		}else{
			// validation has failed, display error messages
			Input::flash();
			return Redirect::to('log/editCat/'.$catID.'/modal')->withErrors($validator);
		}

	}
}
