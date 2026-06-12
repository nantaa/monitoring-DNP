# 8. Code Structure & Patterns — DNP Riksa Uji Monitor

> **Applicable to**: Production build (Laravel 11 + Inertia.js + React/TSX).

---

## 8.1 Project Layout

The system uses a standard Laravel directory structure with Inertia.js for the frontend.

```
dnp-monitor/
├── app/
│   ├── Http/
│   │   ├── Controllers/         # API and Inertia page controllers
│   │   ├── Middleware/          # Security and RBAC guards
│   │   └── Requests/            # FormRequest validation rules
│   ├── Models/                  # Eloquent models
│   ├── Observers/               # Model observers (Audit logs)
│   ├── Policies/                # Authorization logic
│   └── Services/                # Pure business logic (Recommender, Gate checks)
├── bootstrap/                   # Laravel bootstrap (app.php for routing/exceptions in L11)
├── config/                      # Application configuration
├── database/
│   ├── migrations/              # Database schema
│   └── seeders/                 # Test data generators
├── public/                      # Publicly accessible assets (images, compiled JS/CSS)
├── resources/
│   ├── css/
│   │   └── app.css              # PostCSS / Tailwind or Vanilla CSS entry
│   └── js/
│       ├── Components/          # Reusable React components (TSX)
│       │   ├── Common/          # Buttons, Modals, Tags
│       │   ├── Forms/           # Controlled inputs
│       │   └── Layouts/         # Authenticated/Guest layouts
│       ├── Pages/               # Inertia.js Page components (TSX)
│       │   ├── Dashboard/       # Role-based dashboards
│       │   ├── Jobs/            # Job workflow panels
│       │   └── Inventory/       # Master data
│       ├── hooks/               # Custom React hooks
│       ├── lib/                 # Frontend utilities (formatting, client gates)
│       ├── types/               # TypeScript interfaces
│       └── app.tsx              # Inertia.js initialization
├── routes/
│   ├── api.php                  # Mobile/PWA JSON API routes
│   ├── console.php              # Scheduled tasks (Cron)
│   └── web.php                  # Inertia.js routes
└── tests/                       # Pest PHP tests
```

---

## 8.2 Naming Conventions

| Item | Convention | Example |
|------|-----------|---------|
| React components | PascalCase, `.tsx` | `KanbanBoard.tsx` |
| Laravel Controllers | PascalCase, `Controller` suffix | `JobController.php` |
| Eloquent Models | PascalCase, singular | `Job.php`, `AuditLog.php` |
| Database tables | snake_case, plural | `jobs`, `audit_logs` |
| Database columns | snake_case | `tgl_pelaksanaan` |
| Route names | dot.notation | `jobs.index`, `jobs.advance` |
| API Endpoints | kebab-case | `/api/v1/recommend-inspektur` |

---

## 8.3 State Management Pattern

The application uses **Inertia.js**, which bridges Laravel and React without a dedicated API or complex client-side state management (like Redux).

1. **Page Data**: Passed directly from Laravel controllers via `Inertia::render('Jobs/Index', ['jobs' => $jobs])`.
2. **Form State**: Managed using Inertia's `useForm` hook, which handles validation errors automatically.
3. **Global State**: Minimal. User identity and permissions are shared globally via Inertia's `HandleInertiaRequests` middleware (accessible via `usePage().props.auth`).

```tsx
// resources/js/Pages/Jobs/Create.tsx
import { useForm } from '@inertiajs/react';

export default function CreateJob() {
    const { data, setData, post, processing, errors } = useForm({
        klien: '',
        pesawat: 'LIFT',
    });

    function submit(e) {
        e.preventDefault();
        post('/jobs'); // Posts to Laravel, which validates and redirects
    }
}
```

---

## 8.4 Service Layer Pattern

Keep controllers thin. Complex logic (like the Inspector Recommendation Algorithm) goes into `app/Services`.

```php
// app/Services/RecommenderService.php
namespace App\Services;

class RecommenderService
{
    public function recommend(Job $job, array $inspekturList)
    {
        // Pure PHP logic for scoring
        // ...
        return [
            'recommended' => $scored,
            'eliminated' => $eliminated
        ];
    }
}

// app/Http/Controllers/RecommendationController.php
public function __invoke(Job $job, RecommenderService $recommender)
{
    $inspektur = User::where('role', 'inspektur')->get();
    $results = $recommender->recommend($job, $inspektur);
    
    return response()->json($results);
}
```

---

## 8.5 Permission Guard Pattern

Authorization is enforced on the server via **Laravel Policies**.

```php
// app/Policies/JobPolicy.php
public function updateStage7(User $user, Job $job)
{
    return $user->role === 'finance' && $job->stage === 7;
}
```

In the React frontend, check permissions using the shared Inertia props:

```tsx
// resources/js/lib/permissions.ts
import { usePage } from '@inertiajs/react';

export function usePermission() {
    const user = usePage().props.auth.user;
    
    return {
        canEdit: (stageId: number) => user.permissions.includes(`edit_stage_${stageId}`),
        canViewFinancials: () => ['finance', 'manager', 'admin'].includes(user.role),
    };
}
```

---

## 8.6 Stage Gate Logic

Stage transition rules are validated centrally in a FormRequest or a dedicated Service, preventing bypass via API.

```php
// app/Http/Requests/AdvanceJobRequest.php
public function rules()
{
    $job = $this->route('job');
    
    if ($job->stage === 2) {
        return [
            'checklist_po' => 'required|accepted',
            'checklist_permohonan' => 'required|accepted',
            // ...
        ];
    }
    // ...
}
```

---

## 8.7 Audit Log Pattern

Audit logs are generated automatically by **Eloquent Observers**, ensuring no mutation is missed.

```php
// app/Observers/JobObserver.php
namespace App\Observers;
use App\Models\Job;
use App\Models\AuditLog;

class JobObserver
{
    public function updated(Job $job)
    {
        AuditLog::create([
            'table_name' => 'jobs',
            'record_id' => $job->id,
            'operation' => 'UPDATE',
            'old_data' => $job->getOriginal(),
            'new_data' => $job->getAttributes(),
            'changed_by' => auth()->id(),
        ]);
    }
}
```

---

## 8.8 File Upload Pattern

Uploaded files are processed by Laravel and stored on the local VPS disk (`storage/app/public`). File size limits are enforced by Laravel validation rules and Nginx `client_max_body_size`.

```php
// app/Http/Controllers/JobDocumentController.php
public function store(Request $request, Job $job)
{
    $request->validate([
        'document' => 'required|file|mimes:pdf,jpg,png|max:5120', // 5MB
    ]);

    $path = $request->file('document')->store("jobs/{$job->id}/documents", 'public');
    
    $job->documents()->create(['path' => $path]);
    
    return back()->with('success', 'Document uploaded');
}
```
