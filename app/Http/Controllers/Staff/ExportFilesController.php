<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExportFileRequest;
use App\Jobs\GenerateExportFile;
use App\Models\ExportFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportFilesController extends Controller
{
    /**
     * Display a listing of export files.
     */
    public function index(): View
    {
        $module = request()->get('module', 'orders');
        $modules = config('exports.modules', []);

        if (!isset($modules[$module])) {
            $module = 'orders'; // Default to orders
        }

        $moduleConfig = $modules[$module];
        $user = Auth::guard('staff')->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        // Get exports for this module
        $query = ExportFile::where('module', $module);

        // Non-super-admin only see their own exports
        if (!$isSuperAdmin) {
            $query->where('created_by_type', get_class($user))
                ->where('created_by_id', $user->id);
        }

        // Sorting
        $sortBy = request()->get('sort_by', 'created_at');
        $sortDir = request()->get('sort_dir', 'desc');

        // Validate sort direction
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        // Validate sort column
        $allowedSortColumns = ['created_at', 'format', 'rows_count', 'status'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortDir);

        $exports = $query->paginate(3)->withQueryString();

        return view('staff.exports.index', [
            'exports' => $exports,
            'module' => $module,
            'moduleConfig' => $moduleConfig,
            'modules' => $modules,
            'isSuperAdmin' => $isSuperAdmin,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    /**
     * Store a newly created export file request.
     */
    public function store(StoreExportFileRequest $request): RedirectResponse
    {
        $user = Auth::guard('staff')->user();

        $exportFile = ExportFile::create([
            'module' => $request->input('module'),
            'format' => $request->input('format'),
            'filters' => $request->input('filters', []),
            'columns' => $request->input('columns', []),
            'status' => ExportFile::STATUS_PENDING,
            'file_disk' => config('exports.storage_disk', 'local'),
            'created_by_type' => get_class($user),
            'created_by_id' => $user->id,
        ]);

        // Dispatch job to generate the file
        GenerateExportFile::dispatch($exportFile->id);

        return redirect()
            ->route('staff.exports.index', ['module' => $request->input('module')])
            ->with('success', 'Export is being generated. You will be able to download it once ready.');
    }

    /**
     * Download the export file.
     */
    public function download(ExportFile $exportFile): BinaryFileResponse
    {
        $user = Auth::guard('staff')->user();

        // Check authorization
        if (!$exportFile->canBeDownloadedBy($user)) {
            abort(403, 'You do not have permission to download this export.');
        }

        // Check if file is ready
        if (!$exportFile->isReady()) {
            abort(404, 'Export file is not ready yet.');
        }

        // Check if file exists
        if (!Storage::disk($exportFile->file_disk)->exists($exportFile->file_path)) {
            abort(404, 'Export file not found.');
        }

        $filePath = Storage::disk($exportFile->file_disk)->path($exportFile->file_path);
        $fileName = "export_{$exportFile->module}_{$exportFile->id}.{$exportFile->format}";

        return response()->download($filePath, $fileName);
    }

    /**
     * Get exports list as JSON for AJAX updates.
     */
    public function indexJson(): JsonResponse
    {
        $module = request()->get('module', 'orders');
        $modules = config('exports.modules', []);

        if (!isset($modules[$module])) {
            $module = 'orders';
        }

        $user = Auth::guard('staff')->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        $query = ExportFile::where('module', $module);

        if (!$isSuperAdmin) {
            $query->where('created_by_type', get_class($user))
                ->where('created_by_id', $user->id);
        }

        // Sorting
        $sortBy = request()->get('sort_by', 'created_at');
        $sortDir = request()->get('sort_dir', 'desc');

        // Validate sort direction
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        // Validate sort column
        $allowedSortColumns = ['created_at', 'format', 'rows_count', 'status'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortDir);

        $exports = $query->paginate(3)->withQueryString();

        $exportsData = $exports->map(function ($export) {
            return [
                'id' => $export->id,
                'created_at' => $export->created_at->format('Y-m-d H:i:s'),
                'format' => strtoupper($export->format),
                'rows_count' => $export->rows_count ? number_format($export->rows_count) : '—',
                'status' => $export->status,
                'status_label' => ucfirst($export->status),
                'is_ready' => $export->isReady(),
                'download_url' => $export->isReady() ? $export->getDownloadUrl() : null,
                'error' => $export->error,
            ];
        });

        return response()->json([
            'exports' => $exportsData,
            'has_pages' => $exports->hasPages(),
            'current_page' => $exports->currentPage(),
            'last_page' => $exports->lastPage(),
        ]);
    }
}
