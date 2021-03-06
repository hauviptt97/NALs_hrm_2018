<?php
/**
 * Created by PhpStorm.
 * User: Ngoc Quy
 * Date: 4/16/2018
 * Time: 11:26 AM
 */

namespace App\Http\Controllers\User\Employee;


use App\Export\TemplateExport;
use App\Service\ChartService;
use App\Export\InvoicesExport;
use App\Service\SearchEmployeeService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeAddRequest;
use App\Models\Employee;
use App\Models\Team;
use App\Models\Role;
use App\Models\EmployeeType;
use DateTime;
use App\Service\SearchService;
use App\Http\Requests\SearchRequest;
use Illuminate\Support\Facades\Input;
use App\Models\Status;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\EmployeeEditRequest;
use App\Import\ImportFile;
use Illuminate\Support\Facades\Hash;
class EmployeeController extends Controller
{
    /**
     * @var SearchEmployeeServiceProvider
     */
    private $searchEmployeeService;
    protected $searchService;
    private $chartService;

    public function __construct(SearchService $searchService, SearchEmployeeService $searchEmployeeService, ChartService $chartService)
    {
        $this->searchService = $searchService;
        $this->searchEmployeeService = $searchEmployeeService;
        $this->chartService = $chartService;
    }

    public function index(Request $request)
    {
        $status = [0=> "Active", 1=>"Inactive"];
        $roles = Role::select('id', 'name')->where('delete_flag', 0)->get();
        $teams = Team::select('id', 'name')->where('delete_flag', 0)->get();
        if (!isset($request['number_record_per_page'])) {
            $request['number_record_per_page'] = config('settings.paginate');
        }
        $employees = $this->searchEmployeeService->searchEmployee($request)->orderBy('id', 'asc')->paginate($request['number_record_per_page']);
        $employees->setPath('');
        $param = (Input::except(['page','is_employee']));
        return view('employee.list', compact('employees','status', 'roles', 'teams', 'param'));
    }

    public function create()
    {
        $dataTeam = Team::select('id', 'name')->where('delete_flag', 0)->get()->toArray();
        $dataRoles = Role::select('id', 'name')->where('delete_flag', 0)->get()->toArray();
        $dataEmployeeTypes = EmployeeType::select('id', 'name')->where('delete_flag', 0)->get()->toArray();
        return view('employee.add', ['dataTeam' => $dataTeam, 'dataRoles' => $dataRoles, 'dataEmployeeTypes' => $dataEmployeeTypes]);
    }

    public function store(EmployeeAddRequest $request)
    {
        $employee = new Employee;
        $employee->email = $request->email;
        $employee->password = bcrypt($request->password);
        $employee->name = $request->name;
        $employee->birthday = $request->birthday;
        $employee->gender = $request->gender;
        $employee->mobile = $request->mobile;
        $employee->address = $request->address;
        $employee->marital_status = $request->marital_status;
        $employee->startwork_date = $request->startwork_date;
        $employee->endwork_date = $request->endwork_date;
        $date = new DateTime;
        $date = $date->format('Y-m-d H:i:s');
        if(strtotime($employee->endwork_date) < strtotime($date)){
            $employee->work_status = 1;
        }else{
            $employee->work_status = 0;
        }
        $employee->is_employee = 1;
        $employee->employee_type_id = $request->employee_type_id;
        $employee->team_id = $request->team_id;
        $employee->role_id = $request->role_id;
        $employee->created_at = new DateTime();
        $employee->delete_flag = 0;
        
        if($employee->save()){
            \Session::flash('msg_success', 'Account successfully created!!!');
            return redirect('employee');
        }else{
            \Session::flash('msg_fail', 'Account failed created!!!');
            return back()->with(['employee' => $employee]);
        }
        
    }

    public function show($id, SearchRequest $request)
    {
        $data = $request->only([
                'project_name' => $request->get('project_name'),
                'role' => $request->get('role'),
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
                'project_status' => $request->get('project_status'),
                'number_record_per_page' => $request->get('number_record_per_page'),
                'is_employee' => $request->get('is_employee'),
            ]
        );

        $active = $request->get('number_record_per_page');

        if ($active) {
            $active = 'project';
        } else {
            $active = 'basic';
        }

        if (!isset($data['number_record_per_page'])) {
            $data['number_record_per_page'] = config('settings.paginate');
        }

        $data['id'] = $id;

        $processes = $this->searchService->searchProcess($data)->orderBy('project_id', 'desc')->paginate($data['number_record_per_page']);

        $processes->setPath('');

        $param = (Input::except(['page','is_employee']));

        //set employee info
        $employee = Employee::where('delete_flag', 0)->where('is_employee', '1')->find($id);

        $roles = Role::where('delete_flag', 0)->orderBy('name', 'asc')->pluck('name', 'id')->toArray();

        $project_statuses = Status::orderBy('name', 'asc')->pluck('name', 'id')->toArray();

        if (!isset($employee)) {
            return abort(404);
        }

        //set chart
        $year = date('Y');
        $listValue = $this->chartService->getListValueOfMonth($employee, $year);

        //set list years
        $listYears = $this->chartService->getListYear($employee);

        return view('employee.detail', compact('employee',
            'processes',
            'listValue',
            'listYears',
            'roles',
            'param',
            'project_statuses',
            'active'))
            ->render();
    }

    public function edit($id)
    {
        $employee = Employee::where('delete_flag', 0)->where('is_employee',1)->find($id);
        if ($employee == null) {
            return abort(404);
        }
        $objEmployee = Employee::where('delete_flag', 0)->findOrFail($id)->toArray();
        $dataTeam = Team::select('id', 'name')->where('delete_flag', 0)->get()->toArray();
        $dataRoles = Role::select('id', 'name')->where('delete_flag', 0)->get()->toArray();
        $dataEmployeeTypes = EmployeeType::select('id', 'name')->get()->toArray();

        return view('employee.edit', ['objEmployee' => $objEmployee, 'dataTeam' => $dataTeam, 'dataRoles' => $dataRoles, 'dataEmployeeTypes' => $dataEmployeeTypes]);
    }

    public function update(EmployeeEditRequest $request, $id)
    {
        $employee = Employee::where('delete_flag', 0)->where('is_employee',1)->find($id);
        if ($employee == null) {
            return abort(404);
        }
        $employee->email = $request->email;
        $employee->name = $request->name;
        $employee->birthday = $request->birthday;
        $employee->gender = $request->gender;
        $employee->mobile = $request->mobile;
        $employee->address = $request->address;
        $employee->marital_status = $request->marital_status;
        $employee->startwork_date = $request->startwork_date;
        $employee->endwork_date = $request->endwork_date;
        $date = new DateTime;
        $date = $date->format('Y-m-d H:i:s');
        if(strtotime($employee->endwork_date) < strtotime($date)){
            $employee->work_status = 1;
        }else{
            $employee->work_status = 0;
        }
        $employee->employee_type_id = $request->employee_type_id;
        $employee->team_id = $request->team_id;
        $employee->role_id = $request->role_id;
        $employee->updated_at = new DateTime();
        if ($employee->save()) {
            \Session::flash('msg_success', 'Account successfully edited!!!');
            return redirect('employee');
        } else {
            \Session::flash('msg_fail', 'Account failed edited!!!');
            return back()->with(['employee' => $employee]);
        }
    }
    public function editPass(Request $request)
    {
        $employee = Employee::find(\Illuminate\Support\Facades\Auth::user()->id);
        $oldPass = $request -> old_pass;
        $newPass = $request -> new_pass;
        $cfPass = $request -> cf_pass;
        if(!Hash::check($oldPass, $employee -> password)){
            return back()->with(['error' => 'Old password is incorrect!!!', 'employee' => $employee]);
        }else{
            if($newPass != $cfPass){
                return back()->with(['error' => 'The confirm password and password must match!!!', 'employee' => $employee]);
            }else{
                if (strlen($newPass) < 6) {
                    return back()->with(['error' => 'The Password must be at least 6 characters!!!', 'employee' => $employee]);
                }else {
                    $employee->password = bcrypt($newPass);
                    $employee->save();
                    \Session::flash('msg_success', 'Password successfully edited!!!');
                    return redirect('employee/'.$employee->id.'/edit');
                }
            }
        }
    }


    public function destroy($id, Request $request)
    {
        if ($request->ajax()) {
            $employees = Employee::where('id', $id)->where('delete_flag', 0)->first();
            $employees->delete_flag = 1;
            $employees->save();

            return response(['msg' => 'Product deleted', 'status' => 'success', 'id' => $id]);
        }
        return response(['msg' => 'Failed deleting the product', 'status' => 'failed']);
    }


    public function showChart($id, Request $request)
    {
        $year = $request->year;
        $employee = Employee::find($id);
        $listValue = $this->chartService->getListValueOfMonth($employee, $year);
        return response(['listValue' => $listValue]);
    }


    public function postFile(Request $request)
    {
        $listError = "";
        if ($request->hasFile('myFile')) {
            $file = $request->file("myFile");
            if(5242880 < $file->getSize()){ 
                \Session::flash('msg_fail', 'The selected file is too large. Maximum size is 5MB.');
                return redirect('employee');
            }           
            if ($file->getClientOriginalExtension('myFile') == "csv") {
                $nameFile = $file->getClientOriginalName('myFile');
                $file->move('files', $nameFile);

                $importFile = new ImportFile;

                $dataEmployees = $importFile->readFile(public_path('files/' . $nameFile));
                $num = $importFile->countCol(public_path('files/' . $nameFile));

                $templateExport = new TemplateExport;
                $colTemplateExport = $templateExport -> headings();

                $colError = $importFile->checkCol(public_path('files/' . $nameFile),count($colTemplateExport));
                if ($colError != null) {
                    if (file_exists(public_path('files/' . $nameFile))) {
                        unlink(public_path('files/' . $nameFile));
                    }
                    return view('employee.list_import', ['urlFile' => public_path('files/' . $nameFile), 'colError' => $colError, 'listError' => $listError]);
                }
                $row = count($dataEmployees) / $num;
                $listError .= $importFile->checkEmail($dataEmployees, $row, $num);
                $listError .= $importFile->checkFileEmployee($dataEmployees, $num);

                if ($listError != null) {
                    if (file_exists(public_path('files/' . $nameFile))) {
                        unlink(public_path('files/' . $nameFile));
                    }
                }
                $dataTeam = Team::select('id', 'name')->get()->toArray();
                $dataRoles = Role::select('id', 'name')->get()->toArray();
                $dataEmployeeTypes = EmployeeType::select('id', 'name')->get()->toArray();
                return view('employee.list_import', ['dataEmployees' => $dataEmployees, 'num' => $num, 'row' => $row, 'urlFile' => public_path('files/' . $nameFile), 'listError' => $listError, 'colError' => $colError, 'dataTeam' => $dataTeam, 'dataRoles' => $dataRoles, 'dataEmployeeTypes' => $dataEmployeeTypes]);
            } else {
                \Session::flash('msg_fail', 'The file is not formatted correctly!!!');
                return redirect('employee');
            }

        } else {
            \Session::flash('msg_fail', 'File not selected!!!');
            return redirect('employee');
        }
    }

    public function importEmployee()
    {
        $urlFile = $_GET['urlFile'];
        $importFile = new ImportFile;

        $data = $importFile->readFile($urlFile);
        $num = $importFile->countCol($urlFile);
        $row = count($data) / $num;

        for ($row = 1; $row < count($data) / $num; $row++) {
            $c = $row * $num;
            if ($c < $row * ($num + 1)) {
                $employee = new Employee;
                $employee->email = $data[$c];
                $c++;
                $employee->password = bcrypt("123456");
                $employee->name = $data[$c];
                $c++;
                if($data[$c] == "-"){
                    $employee->birthday = null;
                }else{
                    $employee->birthday = date_create($data[$c]);
                }
                $c++;
                
                if (strnatcasecmp($data[$c], "female") == 0) {
                    $employee->gender = 1;
                } else if (strnatcasecmp($data[$c], "male") == 0) {
                    $employee->gender = 2;
                } else {
                    $employee->gender = 3;
                }
                $c++;
                if($data[$c] == "-"){
                    $employee->mobile = "";
                }else{
                    $employee->mobile = $data[$c];
                }
                $c++;
                if($data[$c] == "-"){
                    $employee->address = "";
                }else{
                    $employee->address = $data[$c];
                }
                $c++;
                if($data[$c] == "-"){
                    $employee->marital_status = 1;
                }else{
                    if (strnatcasecmp($data[$c], "single") == 0) {
                        $employee->marital_status = 1;
                    } else if (strnatcasecmp($data[$c], "married") == 0) {
                        $employee->marital_status = 2;
                    } else if (strnatcasecmp($data[$c], "separated") == 0) {
                        $employee->marital_status = 3;
                    } else {
                        $employee->marital_status = 4;
                    }
                }
                $c++;
                if($data[$c] == "-"){
                    $employee->startwork_date = null;
                }else{
                    $employee->startwork_date = date_create($data[$c]);
                }
                $c++;
                if($data[$c] == "-"){
                    $employee->endwork_date = null;
                    $employee->work_status = 0;
                }else{
                    $date = new DateTime;
                    $date = $date->format('Y-m-d H:i:s');
                    $employee->endwork_date = date_create($data[$c]);
                    if(strtotime($data[$c]) < strtotime($date)){
                        $employee->work_status = 1;
                    }else{
                        $employee->work_status = 0;
                    }
                }
                $c++;
                $employee->is_employee = 1;
                $objEmployeeType = EmployeeType::select('name', 'id')->where('name', 'like', $data[$c])->first();
                $employee->employee_type_id = $objEmployeeType->id;
                $c++;
                $objTeam = Team::select('name', 'id')->where('name', 'like', $data[$c])->first();
                $employee->team_id = $objTeam->id;
                $c++;
                $objRole = Role::select('name', 'id')->where('name', 'like', $data[$c])->first();
                $employee->role_id = $objRole->id;
                $c++;
                $employee->created_at = new DateTime();
                $employee->delete_flag = 0;
                $employee->save();
            }
        }
        if (file_exists($urlFile)) {
            unlink($urlFile);
        }
        \Session::flash('msg_success', 'Import Employees successfully!!!');
        return redirect('employee');
    }

    public function export(Request $request)
    {
        $time =(new \DateTime())->format('Y-m-d H:i:s');
        return Excel::download(new InvoicesExport($this->searchEmployeeService, $request), 'employee-list-'.$time.'.csv');
    }

    public function downloadTemplate()
    {
        $time =(new \DateTime())->format('Y-m-d H:i:s');
        return Excel::download(new TemplateExport(), 'employee-template-'.$time.'.csv');
    }
    /*
            ALL DEBUG
            echo "<pre>";
            print_r($employees);
            die;
            var_dump(): user in view;
            dd(); view array
    */
}