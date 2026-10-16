<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Policies\RegistrarPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems',
        'database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
DB::purge('mysql');

$approveHolders = DB::table('role_permissions as rp')->join('roles as r','r.id','=','rp.roleId')
    ->join('permissions as p','p.id','=','rp.permissionId')->where('p.name','enrollment.approve')->pluck('r.name')->all();
echo "enrollment.approve (opens the Registrar screen) roles: ".implode(', ', $approveHolders)."\n\n";

$enr = Enrollments::where('enrollmentStatus','enrolled')->first();
echo "probe enrollment: ".($enr?->enrollmentId ?? 'NONE')."\n";
if (! $enr) { exit(1); }
$policy = app(RegistrarPolicy::class);
printf("%-26s %-6s %-9s %-8s %s\n",'account','office','flag','gate','policy');
foreach (['office1_head','office2_head','office4_head','office11_head','office22_head','registrar_approver'] as $slug) {
    $u = Staffusers::where('email','like',$slug.'@%')->first() ?? Staffusers::where('username',$slug)->first();
    if (! $u) { printf("%-26s (no such account)\n", $slug); continue; }
    printf("%-26s %-6s %-9s %-8s %s\n", $u->email, $u->officeId,
        $u->can('print.enrollmentForm') ? 'ON' : 'off',
        Gate::forUser($u)->allows('registrar.printEnrollmentForm', $enr) ? 'ALLOW' : 'deny',
        $policy->printEnrollmentForm($u, $enr) ? 'ALLOW' : 'deny');
}
