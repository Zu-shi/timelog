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
		Validator::extend('validName', function($attribute, $value, $parameters)
		{
			return Utils::validateName($value);
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

	public function saveEntry($id = null)
	{
		// getPage argument is yet to be used.
		// user must be logged in!
		
		if(!Auth::check()){
			return Response::make('Not Found', 404);
		}

		if($id == null){
			// create new LogEntry
			$entry = new LogEntry;
		}else{
			// load existing LogEntry
			try{
				$entry = LogEntry::where('UID', '=', Auth::user()->id)->findOrFail($id);
			}catch(ModelNotFoundException $e){
				return Response::make('Not Found', 404);
			}
		}
		
		$validator = $this->validateInput(); // validate input from Input::all()
		
		if ($validator->passes()) {
			// validation has passed, save user in DB

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
			$entry->notes = Input::get('notes');
			$entry->UID = Auth::user()->id;
			$entry->save();
			$LID = $entry->LID;
			return array($LID,$validator);
		}

		return array(null,$validator);
	}

	public function saveEntryFromAddPage($id = null){
		$val = $this->saveEntry($id); // returns [LID, $validator], where LID is NULL on error
		if($val[0]){
			return Redirect::to('log/view');
		}else if($id == null) {
			// validation has failed, display error messages
			Input::flash();
			return Redirect::to('log/add')->withErrors($val[1]);
		}else{
			// validation has failed, display error messages
			Input::flash();
			return Redirect::to('log/edit/'.$id)->withErrors($val[1]);
		}
	}

	//This function bypasses returning a page upon successful submission to optimize for speed.
	public function saveEntryFromCalendar($id = null){
		$val = $this->saveEntry($id); // returns [LID, $validator], where LID is NULL on error
		if($val[0]){
			return $val[0];
		}else if($id == null) {
			//TODO: validation has failed, display error messages
			Input::flash();
			return Redirect::to('log/add')->withErrors($val[1]);
		}else{
			//TODO: validation has failed, display error messages
			Input::flash();
			return Redirect::to('log/edit/'.$id)->withErrors($val[1]);
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
			return View::make('entryform')->with('editThis', $entry);
		else
			return View::make('entryform_modal')->with('editThis', $entry);
	}

	public function getLogAdd($modal = false){
		return Auth::check() != null ? ($modal == false ? View::make('entryform')->with('active', 'addlog') : View::make('entryform_modal')->with('active', 'addlog')) : Redirect::to('login');
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


	public function saveCategory($id = null) {
		
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

			
			$superCategory = Input::get('superCategory');

			$catEntry->PID = DB::table('log_category')->where('name',$superCategory)->where('UID',Auth::user()->id)->pluck('CID');
			
			$catEntry->deadline = Input::get('taskDeadline');

			if ($catEntry->deadline == NULL)
				$catEntry->isTask = 0;
			else
				$catEntry->isTask = 1;

			$star = Input::get('starRating');

			if(!$catEntry->isTask == 1){
				$catEntry->isTask = 0;
				$catEntry->isCompleted = 0;
				$catEntry->rating = 0;
			}
			else {
				$catEntry->rating= $star;
				if ($star == 0) {
					$catEntry->isCompleted = 0;
				}
				else {
					$catEntry->isCompleted = 1;
				}
			}

			//save category to database
			$catEntry->save();

			return Redirect::to('log/view');
		} else if($id == null) {
			// validation has failed, display error messages
			Input::flash();
			return Redirect::to('log/addCategory')->withErrors($validator);
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
			$entry->CID = $catID;

			$superCategory = Input::get('subCategory');

			if ($superCategory == null){
				$entry->PID = null;
			}

			else{

				$entry->PID = DB::table('log_category')->where('name',$superCategory)->where('UID',Auth::user()->id)->pluck('CID');
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

			$star = Input::get('starRating');

			if(!$entry->isTask == 1){
				$entry->isTask = 0;
				$entry->isCompleted = 0;
				$entry->rating = 0;
			}
			else {
				$entry->rating= $star;
				if ($star == 0) {
					$entry->isCompleted = 0;
				}
				else {
					$entry->isCompleted = 1;
				}
			}

			//$updateThis = DB::table('log_category')->where('CID', '=', $catID);
			$updateThis = LogCategory::find($entry->CID);



			/*$updateThis->update(array('PID' =>$entry->PID,
									  'name' =>$entry->name,
									  'color' =>$entry->color,
									  'isTask' =>$entry->isTask,
									  'deadline' =>$entry->deadline,
									  'isCompleted' =>$entry->isCompleted,
									  'rating' =>$entry->rating));*/

			$updateThis->PID = $entry->PID;
			$updateThis->name = $entry->name;
			$updateThis->color = $entry->color;
			$updateThis->isTask = $entry->isTask;
			$updateThis->deadline = $entry->deadline;
			$updateThis->isCompleted = $entry->isCompleted;
			$updateThis->rating = $entry->rating;
			$updateThis->save();

			return Redirect::to('log/viewCategory');
		} else if($catID == null) {
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
