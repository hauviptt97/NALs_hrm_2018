<?php

namespace App\Http\Controllers\Absence;

use App\Absence\AbsenceService;
use App\Export\ConfirmExport;
use App\Export\HRAbsenceExport;
use App\Http\Controllers\Controller;
use App\Models\AbsenceStatus;
use App\Models\AbsenceType;
use App\Models\Employee;
use App\Service\AbsenceFormService;
use App\Service\SearchEmployeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use App\Http\Rule\Absence\ValidAbsenceFilter;
use App\Models\Absence;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\AbsenceAddRequest;
use App\Service\AbsencePoTeamService;
use Carbon\Carbon;
use App\Models\Confirm;
use DateTime;
use App\Models\Process;
use App\Models\Role;
use App\Service\SearchConfirmService;

class AbsenceController extends Controller
{
    protected $absenceService;
    private $searchEmployeeService;
    public $id_employee;
    public $absencePoTeamService;
    public $absenceFormService;
    private $searchConfirmService;

    public function __construct(AbsenceService $absenceService,
                                SearchEmployeeService $searchEmployeeService,
                                AbsencePoTeamService $absencePoTeamService,
                                SearchConfirmService $searchConfirmService,
                                AbsenceFormService $absenceFormService)
    {
        $this->searchEmployeeService = $searchEmployeeService;
        $this->absenceService = $absenceService;
        $this->searchConfirmService = $searchConfirmService;
        $this->absencePoTeamService = $absencePoTeamService;
        $this->absenceFormService = $absenceFormService;
    }

    public function indexHR(Request $request)
    {
        $validator = Validator::make(
            $request->input(),
            [
                'month_absence' => new ValidAbsenceFilter(
                    $request->get('year_absence')
                )
            ]
        );
        if ($validator->fails()) {
            view()->share('errors', $validator->errors());
        }
        if (!isset($request['number_record_per_page'])) {
            $request['number_record_per_page'] = config('settings.paginate');
        }
        if (!isset($request['year_absence'])) {
            $request['year_absence'] = date('Y');
        }

        $absenceService = $this->absenceService;
        $month_absences = getArrayMonth();
        $year_absences = $this->absenceService->getArrayYearAbsence();
        $employees = $this->searchEmployeeService->searchEmployee($request)->orderBy('id', 'asc')
            ->paginate($request['number_record_per_page']);
        $employees->setPath('');
        $param = (Input::except(['page', 'is_employee']));
//        session()->flashInput($request->input());
        return view('absences.hr_list', compact('employees', 'param', 'month_absences', 'year_absences','absenceService'));
    }

    public function exportAbsenceHR(Request $request)
    {
            $absences = $request->get('absences');
            dd($absences);
            if(!is_null($absences)){
                $time = (new \DateTime())->format('Y-m-d H:i:s');
                return Excel::download(new HRAbsenceExport($absences), 'absence-list-' . $time . '.csv');
            }else{
                \session()->flash(trans('common.msg_fails'), trans('absence.msg_content.msg_export_fails'));
                redirect(route('absences-hr'))->withInput();
            }
    }


    public function confirmRequest($id, Request $request)
    {
        $absenceType = AbsenceType::where('name', '!=', config('settings.status_common.absence_type.subtract_salary_date'))->get();
        $idPO = Role::where('name', '=', config('settings.Roles.PO'))->first()->id;
        $absenceStatus = AbsenceStatus::all();
        $confirmStatus = AbsenceStatus::all();
        if (!isset($request['number_record_per_page'])) {
            $request['number_record_per_page'] = config('settings.paginate');
        }
        $projects = Process::select('processes.project_id', 'projects.name')
            ->join('projects', 'projects.id', '=', 'processes.project_id')
            ->where('processes.employee_id', '=', $id)
            ->where('processes.role_id', '=', $idPO)
            ->where('processes.delete_flag', '=', '0')
            ->get();
        $listConfirm = $this->searchConfirmService->searchConfirm($request)->where('confirms.employee_id', '=', $id)
            ->where('confirms.is_process', '=', 1)
            ->where('confirms.delete_flag', '=', 0)
            ->orderBy('confirms.id', 'desc')
//            ->get();
            ->paginate($request['number_record_per_page'], ['confirms.*']);
//        dd($listConfirm);
        $listConfirm->setPath('');
//        dd($request);
        $param = (Input::except(['page', 'is_employee']));
//        dd($param);
        return view('absence.po_project', compact('absenceType', 'projects', 'listConfirm', 'idPO',
            'id', 'absenceStatus', 'param', 'confirmStatus'));
    }

    public function confirmRequestAjax($id, Request $request)
    {
        if ($request->ajax()) {
            $typeConfirm = $request->type_confirm;
            $actionConfirm = $request->action_confirm;
            $idConfirm = $request->id_confirm;
            $idAbsence = Confirm::where('id', '=', $idConfirm)->first()->absence_id;
            $rejectReason = $request->reason;
            $idWaiting = AbsenceStatus::where('name', '=', config('settings.status_common.absence.waiting'))->first()->id;
            $idAccept = AbsenceStatus::where('name', '=', config('settings.status_common.absence.accepted'))->first()->id;
            $idReject = AbsenceStatus::where('name', '=', config('settings.status_common.absence.rejected'))->first()->id;

            if($typeConfirm === 'absence'){
                if($actionConfirm === 'accept'){
                    $this->updateConfirm($idConfirm, $idAccept, "");
                    $this->updateStatusAbsence($idAbsence, $idReject, $idWaiting, $idAccept);
                    return response(['msg' => '<span class="label label-success">'.trans('absence_po.list_po.status.absence_accepted').'</span>']);
                } else {
                    $this->updateConfirm($idConfirm, $idReject, $rejectReason);
                    return response(['msg' => '<span class="label label-default">'.trans('absence_po.list_po.status.absence_rejected').'</span>']);
                }
            } else {
                if($actionConfirm === 'accept'){
                    $this->updateConfirm($idConfirm, $idReject, "");
                    return response(['msg' => '<span class="label label-default">'.trans('absence_po.list_po.status.absence_rejected').'</span>']);
                } else {
                    $this->updateConfirm($idConfirm, $idAccept, $rejectReason);
                    $this->updateStatusAbsence($idAbsence, $idReject, $idWaiting, $idAccept);
                    return response(['msg' => '<span class="label label-success">'.trans('absence_po.list_po.status.absence_accepted').'</span>']);
                }
            }
        }
        return response(['msg' => 'Failed']);
    }

    public function updateStatusAbsence($idAbsence, $idReject, $idWaiting, $idAccept){
        $listConfirm = Confirm::where('absence_id', '=', $idAbsence)->get();
        $temp = 0;
        foreach ($listConfirm as $item){
            if($item->absence_status_id == $idReject || $item->absence_status_id == $idWaiting){
                $temp ++;
            }
        }
        if($temp == 0) {
            $absence = Absence::where('id', '=', $idAbsence)->first();
            $absence->absence_status_id = $idAccept;
            $absence->save();
        }
    }

    public function updateConfirm($idConfirm, $idAbsenceStatus, $rejectReason)
    {
        $confirm = Confirm::find($idConfirm);
        $confirm->absence_status_id = $idAbsenceStatus;
        if ($rejectReason != "") {
            $confirm->reason = $rejectReason;
        }
        $confirm->save();

        $absence = $confirm->absence;
        $absence->is_deny = 0;
        $absence->save();
    }

    public function exportConfirmList(Request $request){
        $time =(new \DateTime())->format('Y-m-d H:i:s');
        return Excel::download(new ConfirmExport($request), 'confirm-list-'.$time.'.csv');
//        echo 'hello';
    }


    public function index(Request $request){
        $id = Auth::user()->id;
        $objEmployee = Employee::find($id);
        $startwork_date = (int)date_create($objEmployee->startwork_date)->format("Y");
        $endwork_date = (int)date_create($objEmployee->endwork_date)->format("Y");


        $status = AbsenceStatus::select()->where('name','accepted')->first();
        $type = AbsenceType::select()->where('name','salary_date')->first();

        $dateNow = new DateTime;
        $year = 0;
        if((int)$dateNow->format('Y') < $endwork_date){
             $year = $dateNow->format('Y');
        }else{
            $year = $endwork_date;
        }

        $abc = new \App\Absence\AbsenceService();

        $tongSoNgayDuocNghi = $abc->totalDateAbsences($id, $year); // tong ngay se duoc nghi nam nay
        $soNgayPhepDu = $abc->numberAbsenceRedundancyOfYearOld($id, $year - 1); // ngay phep nam ngoai
        $soNgayPhepCoDinh = $abc->absenceDateOnYear($id, $year) + $abc->numberAbsenceAddPerennial($id, $year); // tong ngay co the duoc nghi


        $tongSoNgayDaNghi = $abc->numberOfDaysOff($id,$year,0,$type->id,$status->id);// tong ngay da nghi phep ( bao gom ngay nghi co luong va` tru luong)

        $soNgayTruPhepDu = $abc->subRedundancy($id, $year); // so ngay tru vao ngay phep du nam ngoai
        $soNgayTruPhepCoDinh = $abc->subDateAbsences($id, $year); // so ngay tru vao ngay phep

        if($year < (int)$dateNow->format('Y') || (int)$dateNow->format('m') > 6){
            $soNgayPhepConLai =  $abc->sumDateExistence($id, $year);
            $checkMonth = 1;
        }else{
            $soNgayPhepConLai =  $abc->sumDateExistence($id, $year) + $abc->sumDateRedundancyExistence($id, $year);
            $checkMonth = 0;
        }
        $soNgayPhepCoDinhConLai = $abc->sumDateExistence($id, $year);
        $soNgayTruPhepDuConLai = $abc->sumDateRedundancyExistence($id, $year);

        $type = AbsenceType::select()->where('name','subtract_salary_date')->first();
        $soNgayNghiTruLuong = $tongSoNgayDaNghi - $soNgayTruPhepDu - $soNgayTruPhepCoDinh + $abc->numberOfDaysOff($id,$year,0,$type->id,$status->id);

        $type = AbsenceType::select()->where('name','insurance_date')->first();

        $soNgayNghiBaoHiem = $abc->numberOfDaysOff($id,$year,0,$type->id,$status->id);

        $absences = [
                        "soNgayDuocNghiPhep"=>$tongSoNgayDuocNghi, 
                        "soNgayNghiPhepCoDinh"=>$soNgayPhepCoDinh,
                        "soNgayPhepDu"=>$soNgayPhepDu,
                        "soNgayDaNghi"=>$tongSoNgayDaNghi,
                        "truVaoPhepCoDinh"=>$soNgayTruPhepCoDinh,
                        "truVaoPhepDu"=>$soNgayTruPhepDu,
                        "soNgayConLai"=>$soNgayPhepConLai,
                        "phepCoDinh"=>$soNgayPhepCoDinhConLai,
                        "phepDu"=>$soNgayTruPhepDuConLai,
                        "soNgayNghiTruLuong"=>$soNgayNghiTruLuong,
                        "soNgayNghiBaoHiem"=>$soNgayNghiBaoHiem
                    ];
        $listAbsence = Absence::select('absence_statuses.name AS name_status','absence_types.name AS name_type','absences.from_date','absences.to_date','absences.reason','absences.description','absences.id')
                ->join('absence_types', 'absences.absence_type_id', '=', 'absence_types.id')
                ->join('absence_statuses', 'absences.absence_status_id', '=', 'absence_statuses.id')
                ->where('absences.delete_flag', 0)
                ->whereYear('absences.from_date', $year)
                ->orWhereYear('absences.to_date', $year)
                ->get();
        return view('vangnghi.list', compact('absences','checkMonth', 'listAbsence', 'objEmployee', 'startwork_date','endwork_date'));
    }

    public function create()
    {
        $id_employee = Auth::user()->id;

        $curDate = date_create(Carbon::now()->format('Y-m-d'));
        $dayBefore = ($curDate)->modify('-15 day')->format('Y-m-d');
        $dayAfter = ($curDate)->modify('+15 day')->format('Y-m-d');

        $objEmployee = Employee::select('employees.*', 'teams.name as team_name')
            ->join('teams', 'employees.team_id', '=', 'teams.id')
            ->where('employees.delete_flag', 0)->find($id_employee);

        $objPO = Employee::SELECT('employees.name as PO_name', 'projects.name as project_name')
            ->JOIN('processes', 'processes.employee_id', '=', 'employees.id')
            ->JOIN('projects', 'processes.project_id', '=', 'projects.id')
            ->JOIN('roles', 'processes.role_id', '=', 'roles.id')
            ->whereIn('processes.project_id', function ($query) use ($id_employee, $dayBefore) {
                $query->select('project_id')
                    ->from('processes')
                    ->where('employee_id', '=', $id_employee)
                    ->whereDate('processes.end_date', '>', $dayBefore);
            })
            ->WHERE('employees.delete_flag', '=', 0)
            ->WHERE('roles.name', 'like', 'po')
            ->get()->toArray();
        $Absence_type = AbsenceType::select('id', 'name')->get()->toArray();

        return view('absences.formVangNghi', ['objPO' => $objPO, 'objEmployee' => $objEmployee, 'Absence_type' => $Absence_type]);
    }

    public function store(AbsenceAddRequest $request){
        return $this->absenceFormService->addNewAbsenceForm($request);
    }

    public function show($id, Request $request)
    {

    }

    public function edit($id)
    {

    }

    public function update(Request $request, $id)
    {

    }

    public function destroy($id, Request $request)
    {

    }

    // function create by Quy.

    public function showListAbsence(Request $request){
        $getIdUserLogged = Auth::id();
        $getAllAbsenceStatus = AbsenceStatus::all();
        $getAllAbsenceTypes = AbsenceType::all();

//        $getAllAbsenceInConfirm = Confirm::where('employee_id',$getIdUserLogged)
//            ->orderBy('id', 'DESC')->get();
        $getAllAbsenceInConfirm = $this->absencePoTeamService->searchAbsence($request, $getIdUserLogged)->orderBy('id', 'DESC')->get();
        return view('absences.poteam', compact('getAllAbsenceInConfirm','getAllAbsenceStatus','getAllAbsenceTypes'));
    }

    public function denyPOTeam(Request $request)
    {
        return $this->absencePoTeamService->poTeamAcceptOrDenyAbsence($request);

    }

    public function doneConfirm(Request $request)
    {
        return $this->absencePoTeamService->poTeamAcceptAbsenceForm($request);
    }
    public function showAbsence($id, Request $request)
    {
        $year = (int)$request->year;
        $dateNow = new DateTime;

        $objEmployee = Employee::find($id);

        $startwork_date = (int)date_create($objEmployee->startwork_date)->format("Y");
        $endwork_date = (int)date_create($objEmployee->endwork_date)->format("Y");


        $status = AbsenceStatus::select()->where('name','accepted')->first();
        $type = AbsenceType::select()->where('name','salary_date')->first();

        $abc = new \App\Absence\AbsenceService();

        $tongSoNgayDuocNghi = $abc->totalDateAbsences($id, $year); // tong ngay se duoc nghi nam nay
        $soNgayPhepDu = $abc->numberAbsenceRedundancyOfYearOld($id, $year - 1); // ngay phep nam ngoai
        $soNgayPhepCoDinh = $abc->absenceDateOnYear($id, $year) + $abc->numberAbsenceAddPerennial($id, $year); // tong ngay co the duoc nghi


        $tongSoNgayDaNghi = $abc->numberOfDaysOff($id,$year,0,$type->id,$status->id);

        $soNgayTruPhepDu = $abc->subRedundancy($id, $year);
        $soNgayTruPhepCoDinh = $abc->subDateAbsences($id, $year);

        if($year < (int)$dateNow->format('Y') || (int)$dateNow->format('m') > 6){
            $soNgayPhepConLai =  $abc->sumDateExistence($id, $year);
            $checkMonth = 1;
        }else{
            $soNgayPhepConLai =  $abc->sumDateExistence($id, $year) + $abc->sumDateRedundancyExistence($id, $year);
            $checkMonth = 0;
        }
        $soNgayPhepCoDinhConLai = $abc->sumDateExistence($id, $year);
        $soNgayTruPhepDuConLai = $abc->sumDateRedundancyExistence($id, $year);

        $soNgayNghiTruLuong = $tongSoNgayDaNghi - $soNgayTruPhepDu - $soNgayTruPhepCoDinh;

        $type = AbsenceType::select()->where('name','insurance_date')->first();

        $soNgayNghiBaoHiem = $abc->numberOfDaysOff($id,$year,0,$type->id,$status->id);

        $absences = [
                        "soNgayDuocNghiPhep"=>$tongSoNgayDuocNghi, 
                        "soNgayNghiPhepCoDinh"=>$soNgayPhepCoDinh,
                        "soNgayPhepDu"=>$soNgayPhepDu,
                        "soNgayDaNghi"=>$tongSoNgayDaNghi,
                        "truVaoPhepCoDinh"=>$soNgayTruPhepCoDinh,
                        "truVaoPhepDu"=>$soNgayTruPhepDu,
                        "soNgayConLai"=>$soNgayPhepConLai,
                        "phepCoDinh"=>$soNgayPhepCoDinhConLai,
                        "phepDu"=>$soNgayTruPhepDuConLai,
                        "soNgayNghiTruLuong"=>$soNgayNghiTruLuong,
                        "soNgayNghiBaoHiem"=>$soNgayNghiBaoHiem
                    ];
        $listAbsence = Absence::select('absence_statuses.name AS name_status','absence_types.name AS name_type','absences.from_date','absences.to_date','absences.reason','absences.description','absences.id', 'absence_statuses.name AS confirm')
                ->join('absence_types', 'absences.absence_type_id', '=', 'absence_types.id')
                ->join('absence_statuses', 'absences.absence_status_id', '=', 'absence_statuses.id')
                ->where('absences.delete_flag', 0)
                ->whereYear('absences.from_date', $year)
                ->orWhereYear('absences.to_date', $year)
                ->get();
        foreach ($listAbsence as $value) {
            $value->name_type = trans('absence_po.list_po.type.'.$value->name_type);
            $value->name_status = trans('absence_po.list_po.status.'.$value->name_status);
            if($value->name_status == trans('absence_po.list_po.status.rejected')){
                $value->confirm = selectConfirm($value->id)->reason;
            }else{
                $value->confirm = "-";
            }
             
        }
        return response(['aAbsences' => $absences, "aListAbsence" => $listAbsence, 'aCheckMonth' => $checkMonth]);
    }
}
