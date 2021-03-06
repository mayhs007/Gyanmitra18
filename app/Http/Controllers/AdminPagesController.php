<?php

namespace App\Http\Controllers;

use Auth;
use Session;
use Request;
use App\Http\Requests\RegistrationRequest;
use App\Http\Requests\TeamRequest;
use App\Http\Requests\CommandRequest;
use App\Confirmation;
use App\User;
use App\Accomodation;
use App\Rejection;
use App\Team;
use App\TeamMember;
use App\Event;
use Illuminate\Support\Facades\Input;
use App\Traits\Utilities;
use App\Payment;
use App\Config;
use App\College;
use App\Department;
use Excel;
use PDF;

class AdminPagesController extends Controller
{
    use Utilities;

    function root(){
        $registered_count = User::where('type', 'student')->where('activated', true)->count();
        $present_count = User::where('type', 'student')->where('present', true)->count();        
        $confirmed_registrations =User::where('type', 'student')->where('confirmation', true)->count();
        $users = User::all()->where('type', 'student')->where('activated', true); 
        $payment_count = Payment::where('payment_status','paid')->count();
        $accomodation_count = Accomodation::count();
        $confirmed_accomodation = Accomodation::where('acc_status', 'ack')->count();
        $accomodation_payment = Accomodation::where('acc_payment_status', 'paid')->count();
        return view('admin_pages.root')->with('registered_count', $registered_count)->with('confirmed_registrations', $confirmed_registrations)->with('payment_count', $payment_count)->with('accomodation_count', $accomodation_count)->with('confirmed_accomodation', $confirmed_accomodation)->with('accomodation_payment', $accomodation_payment)->with('present_count', $present_count);
    }
    function register($user_id){
        $event_id = Input::get('event_id',false);
        if($event_id){
            $event = Event::find($event_id);
            $user = User::find($user_id);
            $user->events()->save($event);
        }
        return redirect()->route('admin::registrations.edit', ['user_id' => $user->id]);
    }
    function editPrizeList(){
        $events = Event::all();
        return view('admin_pages.prize_list')->with('events', $events);
    }
    function updatePrizeList(Request $request){
        $inputs = Request::all();
        if(!isset($inputs['prize-list'])){
            $inputs['prize-list'] = [];
        }
        $events = Event::all()->whereIn('id', $inputs['prize-list']);
        foreach($events as $event){
            $event->show_prize = true;
            $event->update();
        }
        $events = Event::all()->whereNotIn('id', $inputs['prize-list']);        
        foreach($events as $event){
            if($event->show_prize){
                $event->show_prize = false;              
                $event->update();               
            }
        }
        Session::flash('success', 'The prize list was updated');
        return redirect()->route('admin::prizes.list');
    }
    function registerAccomodation($user_id){
        $user = User::findOrFail($user_id);
        $accomodation = new Accomodation();
        $accomodation->acc_status = 'ack';
        $accomodation->acc_payment_status= 'paid';
        $user->accomodation()->save($accomodation);
        return redirect()->route('admin::registrations.edit', ['user_id' => $user->id]);
    }
    function userPresent($user_id){
        $user = User::findOrFail($user_id);
        $user->present = true;
        if($user->update()){
            return response()->json(['error' => false]);                            
        }
        else{
            return response()->json(['error' => true]);                            
        }
    }
    function userAbsent($user_id){
        $user = User::findOrFail($user_id);
        $user->present = false;
        if($user->update()){
            return response()->json(['error' => false]);                            
        }
        else{
            return response()->json(['error' => true]);                            
        }
    }
    function unregister($user_id, $event_id){
        $event = Event::find($event_id);
        $user = User::find($user_id);
        $event->users()->detach($user->id);        
        return  redirect()->route('admin::registrations.edit', ['user_id' => $user_id]);
    }
    function registerTeam($user_id, \Illuminate\Http\Request $request){
        $inputs = Request::all();
        $event = Event::findOrFail($inputs['event_id']);        
        $this->validate($request, [
            'event_id' => 'required',
            'name' => 'required',
            'team_members' => 'required|teamMembersExist|teamMembersCount:' . $event->id
        ]);
        $user = User::findOrFail($user_id);
        $team  = new Team();
        $team->name = $inputs['name'];
        $team->user_id = $user->id;
        $team->save();
        $team_members_emails = explode(',', $inputs['team_members']);
        $team_members_users = User::all()->whereIn('email', $team_members_emails);
        foreach($team_members_users as $team_member_user){
            $team_member = new TeamMember();
            $team_member->team_id = $team->id;
            $team_member->user_id = $team_member_user->id;
            $team->teamMembers()->save($team_member);
        }
        $team->events()->save($event);
        return redirect()->route('admin::registrations.edit', ['user_id' => $user->id]);
    }
    function unregisterTeam($user_id, $event_id){
        $user = User::find($user_id);
        $team = $user->teamLeaderFor($event_id);
        $event  = Event::find($event_id);                         
        $event->teams()->detach($team->id);
        $team->teamMembers()->delete();
        Team::destroy($team->id);
        return  redirect()->route('admin::registrations.edit', ['user_id' => $user->id]);
    }
    function editTeam($id){
        $team = Team::find($id);
        return view('admin_pages.edit_team')->with('team', $team);
    }
    function updateTeam(\Illuminate\Http\Request $request, $id){
        $team  = Team::findOrFail($id);        
        $this->validate($request, [
            'name' => 'required',
            'team_members' => 'required|teamMembersExist|teamMembersCount:' . $team->events->first()->id
        ]);
        $inputs = Request::all();
        $team->teamMembers()->delete();
        $team_members_emails = explode(',', $inputs['team_members']);
        $team_members_users = User::all()->whereIn('email', $team_members_emails);
        foreach($team_members_users as $team_member_user){
            $team_member = new TeamMember();
            $team_member->team_id = $team->id;
            $team_member->user_id = $team_member_user->id;
            $team->teamMembers()->save($team_member);
        } 
        return redirect()->route('admin::registrations.edit', ['user_id' => $team->user->id]); 
    }
    function getAdmins(){
        $adminEmails = User::where('type', 'admin')->get(['email']);
        return response()->json($adminEmails);
    }
    function openRegistrations(){
        Config::setConfig('registration_open', true);
        return redirect()->route('admin::root');
    }
    function closeRegistrations(){
        Config::setConfig('registration_open', false);
        return redirect()->route('admin::root');
    }
    function openAccomodations(){
        Config::setConfig('Accomodation_registration_open', true);
        return redirect()->route('admin::root');
    }
    function closeAccomodations(){
        Config::setConfig('Accomodation_registration_open', false);
        return redirect()->route('admin::root');
    }
    function openDDPayments(){
        Config::setConfig('dd_payment_open', true);
        return redirect()->route('admin::root');
    }
    function closeDDPayments(){
        Config::setConfig('dd_payment_open', false);
        return redirect()->route('admin::root');
    }
    function enableOfflineRegistration(){
        Config::setConfig('offline_link', true);
        return redirect()->route('admin::root');
    }
    function disableOfflineRegistration(){
        Config::setConfig('offline_link', false);
        return redirect()->route('admin::root');
    }
    function new_registration(){
        return view('admin_pages.new_registration');
    }
    function create_registration(RegistrationRequest $request){
        $inputs = Request::all();
        $user = User::create([
            'first_name' => $inputs['first_name'],
            'last_name' => $inputs['last_name'],
            'email' => $inputs['email'],
            'password' => bcrypt('test'),
            'gender' => $inputs['gender'],
            'college_id' => $inputs['college_id'],
            'level_of_study' => $inputs['level_of_study'],
            'mobile' => $inputs['mobile'],
            'type' => 'student',
            'activated' => true,
            'activation_code' => 0
        ]);
        Session::flash('success', 'The user was registered');
        return redirect()->route('admin::registrations.create');
    }
    function registrations(){
        $search = Input::get('search', '');
        $search = $search . '%';
        $user_ids = User::search($search)->pluck('id')->toArray();
        $registrations = User::whereIn('id', $user_ids)->where('activated', true)->where('type','student');
        $registrations_count = $registrations->count();
        $registrations = $registrations->paginate(10);
        return view('admin_pages.registrations')->with('registrations', $registrations)->with('registrations_count', $registrations_count);
    }
    function confirmedRegistrations()
    {
        $search = Input::get('search', '');
        $search = $search . '%';
        $user_ids = User::search($search)->pluck('id')->toArray();
        $registrations = User::whereIn('id', $user_ids)->where('confirmation', true)->where('type','student');
        $registrations_count = $registrations->count();
        $registrations = $registrations->paginate(10);
        return view('admin_pages.registrations')->with('registrations', $registrations)->with('registrations_count', $registrations_count);
    }
    function editRegistration($user_id){
        $user = User::findOrFail($user_id);
        $team = new Team();
        $Workshop = Event::where('category_id', 1)->pluck('title', 'id')->toArray();
        $soloEvents = Event::where('category_id', 2)->where('max_members', 1)->pluck('title', 'id')->toArray();
        $teamEvents = Event::where('max_members', '>', 1)->pluck('title', 'id')->toArray();        
        return view('admin_pages.edit_registration', ['registration' => $user])->with('soloEvents', $soloEvents)->with('teamEvents', $teamEvents)->with('team', $team)->with('workshop',$Workshop);
    }
    function updateRegistration($user_id, RegistrationRequest $request){
        $user = User::findOrFail($user_id);
        $inputs = $request->all();
        $user->first_name = $inputs['first_name'];
        $user->last_name = $inputs['last_name'];
        $user->email = $inputs['email'];
        $user->gender = $inputs['gender'];
        $user->college_id = $inputs['college_id'];
        $user->mobile = $inputs['mobile'];
        $user->update();
        Session::flash('success', 'The user was updated!');
        return redirect()->route('admin::registrations.edit', $user_id);
    }
    function eventRegistrations($event_id){
        $event = Event::findOrFail($event_id);
        $search = Input::get('search', '');
        $search = $search . '%';
        $user_ids = User::search($search)->pluck('id')->toArray();
        if($event->isGroupEvent()){
            $registered_user_ids = $event->teams()->whereIn('user_id', $user_ids)->pluck('user_id')->toArray();
        }
        else{
            $registered_user_ids = $event->users()->whereIn('id', $user_ids)->pluck('id')->toArray();
        }
        $registrations = User::all()->whereIn('id', $registered_user_ids);
        $registrations_count = $registrations->count();        
        // Paginate registrations
        $page = Input::get('page', 1);
        $per_page = 10;
        $registrations = $this->paginate($page, $per_page, $registrations);
        return view('admin_pages.registrations')->with('registrations', $registrations)->with('registrations_count', $registrations_count);
    }
    function confirmPayment($user_id){
        $user = User::findOrFail($user_id);
        $type = Input::get('type', '');
        if($type == 'accomodation'){
            if($user->accomodation && $user->accomodation->acc_status == 'ack'){
                if($user->accomodation->acc_paid){
                    Session('success', 'User has already paid for accomodation');                                 
                }
                else{
                    $user->accomodation->acc_paid = true;
                    $user->accomodation->save();                    
                }
            }
            else{
                Session('success', 'User has no accomodation');                                         
            }
        }
        else{
            if($user->hasPaid()){
                Session('success', 'User has already done his payment');                      
            }
            else{
                  $user->payment->mode_of_payment="spot";
                  $user->payment->payment_status="paid";
                  $user->payment->status="ack";
                  $user->payemnt->user_id=$user->id;
                  $user->payment->amount=$user->getTotalAmount();
               // $this->rejectOtherRegistrations($user->id);
            }
        }
        
        return redirect()->route('admin::registrations.edit', ['user_id' => $user_id]);
    }
    function unconfirmPayment($user_id){
        $user = User::findOrFail($user_id);
        $type = Input::get('type', '');        
        if($type == 'accomodation'){
            if($user->accomodation && $user->accomodation->acc_status == 'ack'){
                if($user->accomodation->acc_paid=='paid'){
                    $user->accomodation->acc_paid = 'notpaid';                    
                    $user->accomodation->save();
                }
                else{
                    $user->accomodation->acc_paid = 'paid';
                    Session('success', 'User has not paid for accomodation');                                    
                }
            }
            else{
                Session('success', 'User has no accomodation');                                         
            }
        }
        else{
            if($user->hasPaid()){
                $user->payment->delete();
            }
            else{
                Session('success', 'User has not done his payment');                                              
            }
        }
       
        return redirect()->route('admin::registrations.edit', ['user_id' => $user_id]);
    }
    function confirmRegistration($user_id){
        $user = User::findOrFail($user_id);
        if($user->hasConfirmed()){
            Session('success', 'User has already confirmed the registration');                      
        }
        else{
            $user->confirmation=true;
            $user->save();
            $payment=new Payment();
            $payment->user_id=$user->id;
            $payment->payment_status='notpaid';
            $payment->status='nack';
            $payment->mode_of_payment='unknown';
            $payment->amount=$user->getTotalAmount();
            $payment->save();
            Session('success', 'You have Confirmed the registration');
        }
        return redirect()->route('admin::registrations.edit', ['user_id' => $user_id]);
    }
    function unconfirmRegistration($user_id){
        $user = User::findOrFail($user_id);
        if($user->hasConfirmed()){
            $user->confirmation=false;
            $user->save();
            $user->payment->delete();
            Session('success', 'You have UnConfirmed the registration');
        }
        else{
            Session('success', 'User has not confirmed the registration');          
        }
        return redirect()->route('admin::registrations.edit', ['user_id' => $user_id]);
    }
    function reports(){
        if(!Auth::user()->hasRole('root') && !Auth::user()->hasRole('hospitality')&&!Auth::user()->hasRole('registration') && !Auth::user()->organizings->count() != 0){
            return redirect()->route('admin::root');
        }
        if(Auth::user()->hasRole('root') ){
            $colleges = ['all' => 'All'];
            $events = ['all' => 'All'];
            $departments=['all' => 'All'];
            $workshops=['all' => 'All'];
            $events=['all' => 'All'];        
            $events += Event::where('category_id',2)->pluck('title', 'id')->sort()->toArray();
            $workshops += Event::where('category_id',1)->pluck('title', 'id')->sort()->toArray();                    
        }else{
            $colleges = ['all' => 'All'];
            $events = [];
            $workshops=[];
            $departments = [];
            $events += Auth::user()->organizings->where('category_id',2)->pluck('title', 'id')->sort()->toArray();
            $workshops +=Auth::user()->organizings->where('category_id',1)->pluck('title', 'id')->sort()->toArray();         
        }
          $colleges += College::pluck('name', 'id')->sort()->toArray();
          $departments += Department::pluck('name', 'id')->toArray();
        return view('admin_pages.reports')->with('colleges', $colleges)->with('events', $events)->with('workshops', $workshops)->with('departments', $departments);
    }
    function reportRegistrations(Request $request){
        $inputs = Request::all();
        $event_id=$inputs['event_id'];
        $college_id = $inputs['college_id'];
        $gender = $inputs['gender'];
        $payment = $inputs['payment'];
        // Get the registered users in the given event
        $user_ids=[];
        if($event_id == "all")
        {
           $events=Event::all()->where('category_id',2);
           $users = User::all()->where('type','student');
           $users = $users->filter(function($user) use ($events){
                    return $user->hasParticipating();
                });
        }
        else{
            if(!Auth::user()->isOrganizing($event_id) && !Auth::user()->hasRole('root')){
                Session::flash('success', 'You dont have rights to view this report!');
                return redirect()->route('admin::root');
            }
            $event = Event::findOrFail($event_id);
            if($event->isGroupEvent()){
                $user_ids = [];
                foreach($event->teams as $team){
                    array_push($user_ids, $team->user_id);
                    foreach($team->teamMembers as $teamMember){
                        array_push($user_ids, $teamMember->user->id);
                    }
                }
                $users = User::all()->whereIn('id', $user_ids);
            }
            else{
                $users = $event->users;
            }
        }
        if($college_id != "all"){
            $users = $users->where('college_id', $college_id);
        }
        if($gender != "all"){
            $users = $users->where('gender', $gender);
        }
        if($payment != "all"){
            $users = $users->filter(function($user) use ($payment){
                return $user->hasPaid() == $payment;
            });
        }
        if($inputs['report_type'] == 'View Report'){
            $users_count = $users->count();
            $page = Input::get('page', 1);
            $per_page = 10;
            $users = $this->paginate($page, $per_page, $users);
            $event_check=true;
            $workshop_check=false;
            return view('admin_pages.report_registrations')->with('users', $users)->with('users_count', $users_count)->with('event_check',$event_check)->with('workshop_check',$workshop_check);            
        }
        else if($inputs['report_type'] == 'Download Excel')
        {
            $usersArray = [];
            foreach($users as $user){
                $userArray['GMID'] = $user->id;                
                $userArray['FirstName'] = $user->first_name;
                $userArray['LastName'] = $user->last_name;
                $userArray['Email'] = $user->email;
                $userArray['College'] = $user->college->name;
                $userArray['Gender'] = $user->gender;
                if($user->hasEvents())
                {   
                    $events = $user->events()->where('category_id',2)->pluck('title');
                    $evente=" ";
                    foreach($events as $event)
                    {
                        $evente.=$event;
                        $evente.=',';
                    }
                    $userArray['Events']=$evente;
                        
                }
                else{
                    $userArray['Events']='-';
                }
                if($user->TeamEvents()->count() >0 )
                {   
                    $events = $user->TeamEvents();
                    $evente=" ";
                    foreach($events as $event)
                    {
                        $evente.=$event->title;
                        $evente.=',';
                    }
                    $userArray['Team Events']=$evente;
                        
                }
                else{
                    $userArray['Team Events']='-';
                }
                $userArray['Mobile'] = $user->mobile;
                $userArray['Payment'] = $user->hasPaid()? 'Paid': 'Not Paid';
                if($user->hasPaid())
                {
                    if($user->payment->mode_of_payment=='online')
                    {
                        $userArray['Mode of Payment']="Online";
                        $userArray['Amount'] = $user->getTotalAmountForOnline();
                    }
                    elseif($user->payment->mode_of_payment=='dd')
                    {
                        $userArray['Mode of Payment']="DD";
                        $userArray['Amount'] = $user->getTotalAmount();
                    }
                    else
                    {   $userArray['Mode of Payment']="-";
                        $userArray['Amount'] = "NOT PAID";
                    }
                }
                else
                {   
                    $userArray['Mode of Payment']="-";
                    $userArray['Amount'] = "NOT PAID";
                }
                array_push($usersArray, $userArray);
            }
            Excel::create('Event_report', function($excel) use($usersArray){
                $excel->sheet('Sheet1', function($sheet) use($usersArray){
                    $sheet->fromArray($usersArray);
                });
            })->download('xlsx');
        }
        else if($inputs['report_type'] == 'Download Pdf' && $inputs['event_id']!="all")
        {
            $event=Event::findOrFail($inputs['event_id']);
            $pdf = PDF::loadView('admin_pages.attendance', ['users' => $users,'event'=>$event]);
          return $pdf->download('Attendance.pdf');
        }
    }
    
    function reportWorkshopRegistrations(Request $request){
        $inputs = Request::all();
        $workshop_id=$inputs['workshop_id'];
        $college_id = $inputs['college_id'];
        $gender = $inputs['gender'];
        $payment = $inputs['payment'];
        // Get the registered users in the given event
        $registered_user_ids=[];
        $user_ids=[];
        if($workshop_id == "all")
        {
            $workshops=Event::all()->where('category_id',1);
           $users = User::all()->where('type','student');
           $users = $users->filter(function($user) use ($workshops){
            return $user->hasWorkshop();
        });
        }
        else{
            if(!Auth::user()->isOrganizing($workshop_id) && !Auth::user()->hasRole('root')){
                Session::flash('success', 'You dont have rights to view this report!');
                return redirect()->route('admin::root');
            }
            $event = Event::findOrFail($workshop_id);
            $users = $event->users;
        }
        if($college_id != "all"){
            $users = $users->where('college_id', $college_id);
        }
        if($gender != "all"){
            $users = $users->where('gender', $gender);
        }
        if($payment !="all" ){
            $users = $users->filter(function($user) use ($payment){
                return $user->hasPaid() == $payment;
            });
        }
  
        if($inputs['report_type'] == 'View Report'){
            $users_count = $users->count();
            $page = Input::get('page', 1);
            $per_page = 10;
            $users = $this->paginate($page, $per_page, $users);
            $event_check=false;
            $workshop_check=true;
            return view('admin_pages.report_registrations')->with('users', $users)->with('users_count', $users_count)->with('event_check',$event_check)->with('workshop_check',$workshop_check);            
        }
        else if($inputs['report_type'] == 'Download Excel'){
            $usersArray = [];
            //$college_id=$college_id->sort();
            
            foreach($users as $user){
                $userArray['GMID'] = $user->id;                
                $userArray['FirstName'] = $user->first_name;
                $userArray['LastName'] = $user->last_name;
                $userArray['Email'] = $user->email;
                $userArray['College'] = $user->college->name;
                $userArray['Gender'] = $user->gender;
                if($user->hasWorkshop())
                {   
                    $workshops = $user->events()->where('category_id',1)->pluck('title');
                    $workshopss=" ";
                    foreach($workshops as $workshop)
                    {
                        
                        $workshopss.=$workshop; 
                        $workshopss.=',';    
                    }
                    $userArray['Workshop']=$workshopss;
                }
                else{
                    $userArray['Workshop']='-';
                }
                $userArray['Mobile'] = $user->mobile;
                $userArray['Payment'] = $user->hasPaid()? 'Paid': 'Not Paid';
                if($user->hasPaid())
                {
                    if($user->payment->mode_of_payment=='online')
                    {
                        $userArray['Mode of Payment']="Online";
                        $userArray['Amount'] = $user->getTotalAmountForOnline();
                    }
                    elseif($user->payment->mode_of_payment=='dd')
                    {
                        $userArray['Mode of Payment']="DD";
                        $userArray['Amount'] = $user->getTotalAmount();
                    }
                    else
                    {   $userArray['Mode of Payment']="-";
                        $userArray['Amount'] = "NOT PAID";
                    }
                }
                else
                {
                    $userArray['Mode of Payment']="-";
                    $userArray['Amount'] = "NOT PAID";
                }
                array_push($usersArray, $userArray);
            }
            Excel::create('Workshop_report', function($excel) use($usersArray){
                $excel->sheet('Sheet1', function($sheet) use($usersArray){
                    $sheet->fromArray($usersArray);
                });
            })->download('xlsx');
        }else if($inputs['report_type'] == 'Download Pdf' && $inputs['workshop_id']!="all")
        {
          $workshop=Event::findOrFail($inputs['workshop_id']);
        $pdf = PDF::loadView('admin_pages.attendance', ['users' => $users,'event'=>$workshop]);
          return $pdf->download('Attendance.pdf');
        }
        else
        {
            if($inputs['report_type'] == 'Download College')
            {
                $colleges=College::all()->sort();
                //$colleges=College::all()->sort();
                $usersArray = [];
                //$usersArray["College_name"]=[];
                //$usersArray["Count"]=[];
                //$count=0;
                foreach($colleges as $college)
                {
                
                    Excel::create('College', function($excel) use($usersArray){
                        $excel->sheet('Sheet1', function($sheet) use($usersArray){
                            $sheet->fromArray($usersArray);
                        });
                    })->download('xlsx');
                }
        }}
    }
   
   
   
    function reportDomainRegistrations(Request $request){
        $inputs = Request::all();
        $department_id=$inputs['department_id'];
        $college_id = $inputs['college_id'];
        $gender = $inputs['gender'];
        $payment = $inputs['payment'];
        // Get the registered users in the given event
        $registered_user_ids=[];
        $user_ids=[];    
        $users = User::all()->where('type', 'student');
        $events=Event::all();
        if($department_id == "all")
        {
            $users = $users->filter(function($user) use ($payment){
                return $user->isParticipating();
            });
        }
        else
        {
            $event_ids=[];
            $events = $events->where('department_id',$department_id);
            foreach($events as $event)
            {
                array_push($event_ids,$event->id);
            }
            foreach($event_ids as $event_id)
            {
                $event=Event::findOrFail($event_id);
                
                    if($event->isGroupEvent())
                    {
                        foreach($event->teams as $team)
                        {
                            array_push($user_ids, $team->user_id);
                            foreach($team->teamMembers as $teamMember)
                            {
                                array_push($user_ids, $teamMember->user->id);
                            }
                        }
                    }
                    else
                    {
                        foreach($event->users as $user)
                        {
                         array_push($user_ids,$user->id);
                        }
                    }
                }
            $users=User::all()->whereIn('id',$user_ids);
        
        }
        if($college_id != "all"){
            $users = $users->where('college_id', $college_id);
        }
        if($gender != "all"){
            $users = $users->where('gender', $gender);
        }
        if($payment != "all"){
            $users = $users->filter(function($user) use ($payment){
                return $user->hasPaid() == $payment;
            });
        }
        if($inputs['report_type'] == 'View Report'){
            $users_count = $users->count();
            $page = Input::get('page', 1);
            $per_page = 10;
            $users = $this->paginate($page, $per_page, $users);
            $event_check=true;
            $workshop_check=true;
            return view('admin_pages.report_registrations')->with('users', $users)->with('users_count', $users_count)->with('event_check',$event_check)->with('workshop_check',$workshop_check);       
        }
        else if($inputs['report_type'] == 'Download Excel'){
            $usersArray = [];
            foreach($users as $user){
                $userArray['GMID'] = $user->id;                
                $userArray['FirstName'] = $user->first_name;
                $userArray['LastName'] = $user->last_name;
                $userArray['Email'] = $user->email;
                $userArray['College'] = $user->college->name;
                $userArray['Gender'] = $user->gender;
                if($user->hasWorkshop())
                {   
                    $workshops = $user->events()->where('category_id',1)->pluck('title');
                    $workshopss=" ";
                    foreach($workshops as $workshop)
                    {
                        
                        $workshopss.=$workshop;    
                    }
                    $userArray['Workshop']=$workshopss;
                }
                else{
                    $userArray['Workshop']='-';
                }
                if($user->hasEvents())
                {   
                    $events = $user->events()->where('category_id',2)->pluck('title');
                    $evente=" ";
                    foreach($events as $event)
                    {
                        $evente.=$event;
                        $evente.=',';
                    }
                    $userArray['Events']=$evente;
                        
                }
                else
                {
                    $userArray['Events']='-';
                }
                if($user->TeamEvents()->count() >0 )
                {   
                    $events = $user->TeamEvents();
                    $evente=" ";
                    foreach($events as $event)
                    {
                        $evente.=$event->title;
                        $evente.=',';
                    }
                    $userArray['Team Events']=$evente;
                        
                }
                else{
                    $userArray['Team Events']='-';
                }
                $userArray['Mobile'] = $user->mobile;
                $userArray['Payment'] = $user->hasPaid()? 'Paid': 'Not Paid';
                if($user->hasPaid())
                {
                    if($user->payment->mode_of_payment=='online')
                    {
                        $userArray['Mode of Payment']="Online";
                        $userArray['Amount'] = $user->getTotalAmountForOnline();
                    }
                    elseif($user->payment->mode_of_payment=='dd')
                    {
                        $userArray['Mode of Payment']="DD";
                        $userArray['Amount'] = $user->getTotalAmount();
                    }
                    else
                    {   $userArray['Mode of Payment']="-";
                        $userArray['Amount'] = "NOT PAID";
                    }
                }
                else
                {
                    $userArray['Mode of Payment']="-";
                    $userArray['Amount'] = "NOT PAID";
                }
                array_push($usersArray, $userArray);
            }
            Excel::create('Domain_report', function($excel) use($usersArray){
                $excel->sheet('Sheet1', function($sheet) use($usersArray){
                    $sheet->fromArray($usersArray);
                });
            })->download('xlsx');
        }
    }
    function reportallRegistrations(Request $request){
        $inputs = Request::all();
        $users=User::all()->where('type','student')->where('activated',true)->where('confirmation',true);
        $users = $users->filter(function($user){
                return $user->hasPaid() == 'paid';
        });
        $users=$users->filter(function($user){
            return $user->hasRegisteredBoth();
        });
        $registered_both=$users;
        $users=User::all()->where('type','student')->where('activated',true)->where('confirmation',true);
        $users = $users->filter(function($user){
            return $user->hasPaid() == 'paid';
        });
        $users = $users->filter(function($user){
            return !$user->hasWorkshop();
        });
        //$spot_registeration=$users;
        $user_ids=[];
        foreach($registered_both as $user)
        {
            array_push($user_ids,$user->id);
        }
        foreach($users as $user)
        {
            array_push($user_ids,$user->id);
        }
        $users=User::all()->whereIn('id',$user_ids);
        if($inputs['report_type'] == 'View Report')
        {
            $users_count = $users->count();
            $page = Input::get('page', 1);
            $per_page = 10;
            $users = $this->paginate($page, $per_page, $users);
            $event_check=true;
            $workshop_check=true;
            return view('admin_pages.report_registrations')->with('users', $users)->with('users_count', $users_count)->with('event_check',$event_check)->with('workshop_check',$workshop_check);            
        }
        else if($inputs['report_type'] == 'Download Excel'){
            $usersArray = [];
            foreach($users as $user){
                $userArray['GMID'] = $user->id;                
                $userArray['FirstName'] = $user->first_name;
                $userArray['LastName'] = $user->last_name;
                $userArray['Email'] = $user->email;
                $userArray['College'] = $user->college->name;
                $userArray['Gender'] = $user->gender;
            /*    if($user->hasWorkshop())
                {   
                    $workshops = $user->events()->where('category_id',1)->pluck('title');
                    $workshopss=" ";
                    foreach($workshops as $workshop)
                    {
                        
                        $workshopss.=$workshop;    
                    }
                    $userArray['Workshop']=$workshopss;
                }
                else{
                    $userArray['Workshop']='-';
                }
                if($user->hasEvents())
                {   
                    $events = $user->events()->where('category_id',2)->pluck('title');
                    $evente=" ";
                    foreach($events as $event)
                    {
                        $evente.=$event;
                        $evente.=',';
                    }
                    $userArray['Events']=$evente;
                        
                }
                else{
                    $userArray['Events']='-';
                }
                if($user->TeamEvents()->count() >0 )
                {   
                    $events = $user->TeamEvents();
                    $evente=" ";
                    foreach($events as $event)
                    {
                        $evente.=$event->title;
                        $evente.=',';
                    }
                    $userArray['Team Events']=$evente;
                        
                }
                else{
                    $userArray['Team Events']='-';
                }
                */$userArray['Mobile'] = $user->mobile;
                $userArray['Payment'] = $user->hasPaid()? 'Paid': 'Not Paid';
                if($user->hasPaid())
                {
                    if($user->payment->mode_of_payment=='online')
                    {
                        $userArray['Mode of Payment']="Online";
                        $userArray['Amount'] = $user->getTotalAmountForOnline();
                    }
                    elseif($user->payment->mode_of_payment=='dd')
                    {
                        $userArray['Mode of Payment']="DD";
                        $userArray['Amount'] = $user->getTotalAmount();
                    }
                    elseif($user->payment->mode_of_payment="spot")
                    {   $userArray['Mode of Payment']="SPOT";
                        $userArray['Amount'] =200;
                    }
                }
                else
                {
                    $userArray['Mode of Payment']="-";
                    $userArray['Amount'] = "NOT PAID";
                }
                array_push($usersArray, $userArray);
            }
            Excel::create('All_Registration_report', function($excel) use($usersArray){
                $excel->sheet('Sheet1', function($sheet) use($usersArray){
                    $sheet->fromArray($usersArray);
                });
            })->download('xlsx');
        }elseif($inputs['report_type'] == 'Download College Count')
        {
            $usersArray['College_name']=[];
            $usersArray['Count']=[];
            $count=0;
            $colleges=College::all()->sort();
            foreach($colleges as $college)
            {
                if($college->users->count()>0)
                {
                    array_push($usersArray['College_name'],$college->name);
                    $users=$college->users;
                    foreach($users as $user)
                    {
                        if(!$user->hasWorkshop() && $user->hasPaid())
                        {
                            $count++;
                        }
                    }
                    array_push($usersArray['Count'],$count);
                    $count=0;
                }
            }
            Excel::create('College_report', function($excel) use($usersArray){
                $excel->sheet('Sheet1', function($sheet) use($usersArray){
                    $sheet->fromArray($usersArray);
                });
            })->download('xlsx');
        }elseif($inputs['report_type'] == 'Event Count')
        {
            $usersArray['Event_name']=[];
            $usersArray['Domain_name']=[];
            $usersArray['Count']=[];
            $events=Event::all()->where('category_id',2);
            $count=0;
            foreach($events as $event)
            {
                if($event->isGroupEvent())
                {
                    array_push($usersArray['Event_name'],$event->title);
                    array_push( $usersArray['Domain_name'],$event->department->name);
                    foreach($event->users as $user)
                    {
                        if($user->hasPaid())
                        {
                            $count++;
                        }
                    }
                    array_push( $usersArray['Count'],$count);
                    $count=0;
                }else
                {
                    array_push($usersArray['Event_name'],$event->title);
                    array_push( $usersArray['Domain_name'],$event->department->name);
                    foreach($event->users as $user)
                    {
                        if($user->hasPaid())
                        {
                            $count++;
                        }
                    }
                    array_push( $usersArray['Count'],$count);
                    $count=0;
                }
            }
            Excel::create('Event_Count_report', function($excel) use($usersArray){
                $excel->sheet('Sheet1', function($sheet) use($usersArray){
                    $sheet->fromArray($usersArray);
                });
            })->download('xlsx');
        }
    }
    function reportAccomodations(Request $request){
        if(!Auth::user()->hasRole('hospitality') && !Auth::user()->hasRole('root')){
            Session::flash('success', 'You dont have rights to view this report!');
            return redirect()->route('admin::root');
        }
        $inputs = Request::all();
        $college_id = $inputs['college_id'];
        $gender = $inputs['gender'];
        $payment = $inputs['payment'];
        $status = $inputs['status'];
        // Get the user who requested for accomodations
        $user_ids = Accomodation::pluck('user_id')->toArray();
        $users = User::all()->whereIn('id', $user_ids);
        if($college_id != "all"){
            $users = $users->where('college_id', $college_id);
        }
        if($gender != "all"){
            $users = $users->where('gender', $gender);
        }
        if($status != "all"){
            $users = $users->filter(function($user) use ($status){
                return $user->accomodation->status == $status;
            });
        }
        if($payment != "all"){
            $users = $users->filter(function($user) use($payment){
                return $user->accomodation->paid == $payment;
            });
        }
        if($inputs['report_type'] == 'View Report'){
            $users_count = $users->count();
            $page = Input::get('page', 1);
            $per_page = 10;
            $users = $this->paginate($page, $per_page, $users);
            return view('admin_pages.report_accomodations')->with('users', $users)->with('users_count', $users_count);        
        }
        else if($inputs['report_type'] == 'Download Excel'){
            $usersArray = [];
            foreach($users as $user){
                $userArray['GMID'] = $user->id;                
                $userArray['FirstName'] = $user->first_name;
                $userArray['lastName'] = $user->last_name;
                $userArray['Email'] = $user->email;
                $userArray['College'] = $user->college->name;                
                $userArray['Gender'] = $user->gender;                
                $userArray['Mobile'] = $user->mobile;
                $userArray['Days'] = $user->accomodation->days;
                if($user->accomodation->status){
                    $userArray['Status'] = $user->accomodation->status == 'ack'? 'Accepted':'Rejected';  
                }
                else{
                    $userArray['Status'] = 'Yet to be acknowledged';                          
                }
                $userArray['Payment'] = $user->accomodation->paid? 'Paid': 'Not Paid';
                array_push($usersArray, $userArray);
            }
            Excel::create('accomodation_report', function($excel) use($usersArray){
                $excel->sheet('Sheet1', function($sheet) use($usersArray){
                    $sheet->fromArray($usersArray);
                });
            })->download('xlsx');
        }
    }
    function allRequests(){
        $search = Input::get('search', '');
        $search = $search . '%';
        $user_ids = User::search($search)->pluck('id')->toArray();
        $requests = User::all()->where('confirmation',true);
        $requests_count = $requests->count();
        $page = Input::get('page', 1);
        $per_page = 10;
        $requests = $this->paginate($page, $per_page, $requests);
        return view('admin_pages.requests')->with('requests', $requests)->with('requests_count', $requests_count);
    }
    function requests(){
        $search = Input::get('search', '');
        $search = $search . '%';
        $user_ids = User::search($search)->pluck('id')->toArray();
        $requests = Payment::all();
      
        $requests_count = $requests->count();        
        $page = Input::get('page', 1);
        $per_page = 10;
        $requests = $this->paginate($page, $per_page, $requests);
        return view('admin_pages.requests')->with('requests', $requests)->with('requests_count', $requests_count);
    }
    function eventRequests($event_id){
        $event = Event::findOrFail($event_id);
        $search = Input::get('search', '');
        $search = $search . '%';
        $user_ids = User::search($search)->pluck('id')->toArray();
        if($event->isGroupEvent()){
            $registered_user_ids = $event->teams()->whereIn('user_id', $user_ids)->pluck('user_id')->toArray();
        }
        else{
            $registered_user_ids = $event->users()->whereIn('id', $user_ids)->pluck('id')->toArray();
        }
        $requests = Confirmation::all()->where('status', null)->where('file_name', '<>',  null)->whereIn('user_id', $registered_user_ids)->filter(function($confirmation){
            return $confirmation->user->needApproval();
        });
        $requests_count = $requests->count();                
        $page = Input::get('page', 1);
        $per_page = 10;
        $requests = $this->paginate($page, $per_page, $requests);
        return view('admin_pages.requests')->with('requests', $requests)->with('requests_count', $requests_count);
    }
    function replyRequest(Request $request){
        $inputs = Request::all();
        $user_id = $inputs['user_id'];
        $user = User::find($user_id);
        if($inputs['submit'] == 'Accept'){
            $user->payment->mode_of_payment="spot";
            $user->payment->payment_status="paid";
            $user->payment->status="ack";
            $user->payment->user_id=$user->id;
            $user->payment->amount=200;

        }
        else if($inputs['submit'] == 'Reject'){
            $user->payment->status = 'nack';
            $user->payment->payment_status='notpaid';
        }
        $user->payment->save();
        return redirect()->back();
    }
    function allAccomodationRequests(){
        $search = Input::get('search', '');
        $search = $search . '%';
        $user_ids = User::search($search)->pluck('id')->toArray();
        $requests = Accomodation::whereIn('user_id', $user_ids)->paginate(10);
        return view('admin_pages.accomodations')->with('requests', $requests);
    }
    function accomodationRequests(){
        $search = Input::get('search', '');
        $search = $search . '%';
        $user_ids = User::search($search)->pluck('id')->toArray();
        $requests = Accomodation::where('status', null)->whereIn('user_id', $user_ids)->paginate(10);
        return view('admin_pages.accomodations')->with('requests', $requests);
    }
    function replyAccomodationRequest(Request $request){
        $inputs = Request::all();
        $user_id = $inputs['user_id'];
        $user = User::find($user_id);
        if($inputs['submit'] == 'Accept'){
            $user->accomodation->acc_status = 'ack';
            $user->accomodation->acc_payment_status = 'paid';
        }
        else if($inputs['submit'] == 'Reject'){
            $user->accomodation->acc_status = 'nack';
            $user->accomodation->acc_payment_status = 'notpaid';
            
        }
        $user->accomodation->save();
        return redirect()->back();
    }
    function terminal(){
        return view('admin_pages.terminal');
    }
    function executeCommand(CommandRequest $request){
        $inputs = $request->all();
        $output = [];
        exec($inputs['command'], $output);
        return view('admin_pages.terminal')->with('output', implode("<br>", $output));
    }
    function paymentsonline()
    {
        $search = Input::get('search', '');
        $search = $search . '%';
        $payments=Payment::all()->where('mode_of_payment','online');
        $payments_count = $payments->count();
        //$payments = $payments->paginate(10);
        return view('admin_pages.payments')->with('registrations', $payments)->with('registrations_count',$payments_count);
    }
    
    
}
