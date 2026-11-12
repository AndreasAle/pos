@if(session('success'))<div role="status" class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">{{ session('success') }}</div>@endif
@if($errors->any())<div role="alert" class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@if(session('report_error'))<div role="alert" class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">{{ session('report_error') }}</div>@endif
