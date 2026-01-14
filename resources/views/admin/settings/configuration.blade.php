@extends('layouts.app')

@section('title', 'Settings - Configuration')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Settings / Configuration</h1>
  </div>

  @if ($errors->has('config'))
    <div class="alert alert-danger">{{ $errors->first('config') }}</div>
  @endif

  @if ($errors->any() && !$errors->has('config'))
    <div class="alert alert-danger">
      <strong>Please fix the errors below.</strong>
    </div>
  @endif

  <style>
    .config-card-header[role="button"] { cursor: pointer; user-select: none; }
    .config-chevron { transition: transform .15s ease-in-out; }
    .config-card-header[aria-expanded="true"] .config-chevron { transform: rotate(180deg); }
  </style>

  <div class="accordion" id="configAccordion">

    {{-- =========================
         System
    ========================== --}}
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between config-card-header"
           role="button"
           data-bs-toggle="collapse"
           data-bs-target="#systemCollapse"
           aria-expanded="false"
           aria-controls="systemCollapse">
        <div>
          <strong>System</strong>
          <div class="text-muted small">Branding & display</div>
        </div>
        <i class="fas fa-chevron-down config-chevron"></i>
      </div>

      <div id="systemCollapse" class="collapse" data-bs-parent="#configAccordion">
        <div class="card-body">

          <form method="POST" action="{{ route('admin.settings.config.system.update') }}">
            @csrf
            @method('PUT')

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Header Display Name</label>
                <input class="form-control"
                       name="header_name"
                       value="{{ old('header_name', $system?->header_name ?? config('app.name')) }}"
                       required>
                @error('header_name') <div class="text-danger small">{{ $message }}</div> @enderror
                <div class="text-muted small mt-1">Shown at the top-left of the portal.</div>
              </div>

              <div class="col-md-6 mb-3">
                <label class="form-label">Footer Display Name</label>
                <input class="form-control"
                       name="footer_name"
                       value="{{ old('footer_name', $system?->footer_name ?? config('app.name')) }}"
                       required>
                @error('footer_name') <div class="text-danger small">{{ $message }}</div> @enderror
                <div class="text-muted small mt-1">Shown in the footer.</div>
              </div>
            </div>

            <button class="btn btn-primary" type="submit">
              <i class="fas fa-save"></i> Save System Settings
            </button>
          </form>

        </div>
      </div>
    </div>
    {{-- =========================
         Income Sources
    ========================== --}}
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between config-card-header"
           role="button"
           data-bs-toggle="collapse"
           data-bs-target="#incomeSourcesCollapse"
           aria-expanded="false"
           aria-controls="incomeSourcesCollapse">
        <div>
          <strong>Income Sources</strong>
          <div class="text-muted small">Used on Income entries</div>
        </div>
        <i class="fas fa-chevron-down config-chevron"></i>
      </div>

      <div id="incomeSourcesCollapse" class="collapse" data-bs-parent="#configAccordion">
        <div class="card-body">

          {{-- Add new --}}
          <form method="POST" action="{{ route('admin.settings.config.income_sources.store') }}" class="mb-3">
            @csrf
            <div class="row align-items-end">
              <div class="col-md-5 mb-2">
                <label class="form-label">Name</label>
                <input class="form-control" name="name" value="{{ old('name') }}" required>
              </div>
              <div class="col-md-2 mb-2">
                <label class="form-label">Sort</label>
                <input class="form-control" type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" max="9999">
              </div>
              <div class="col-md-2 mb-2">
                <div class="form-check mt-4">
                  <input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="income_active_new">
                  <label class="form-check-label" for="income_active_new">Active</label>
                </div>
              </div>
              <div class="col-md-3 mb-2">
                <button class="btn btn-primary w-100" type="submit">
                  <i class="fas fa-plus"></i> Add Income Source
                </button>
              </div>
            </div>
          </form>

          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th style="width:40%">Name</th>
                  <th style="width:15%">Sort</th>
                  <th style="width:15%">Active</th>
                  <th class="text-end" style="width:30%">Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($incomeSources as $row)
                  <tr>
                    <td>
                      <form method="POST" action="{{ route('admin.settings.config.income_sources.update', $row) }}" class="d-flex gap-2">
                        @csrf
                        @method('PUT')
                        <input class="form-control form-control-sm" name="name" value="{{ old('name', $row->name) }}" required>
                    </td>
                    <td>
                        <input class="form-control form-control-sm" type="number" name="sort_order" value="{{ old('sort_order', $row->sort_order ?? 0) }}" min="0" max="9999">
                    </td>
                    <td>
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked((int)$row->is_active === 1) id="income_active_{{ $row->id }}">
                          <label class="form-check-label" for="income_active_{{ $row->id }}">Yes</label>
                        </div>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" type="submit">
                          <i class="fas fa-save"></i> Save
                        </button>
                      </form>

                      <form method="POST" action="{{ route('admin.settings.config.income_sources.destroy', $row) }}" class="d-inline"
                            onsubmit="return confirm('Delete this income source?');">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" type="submit">
                          <i class="fas fa-trash"></i> Delete
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-muted">No income sources found.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>

    {{-- =========================
         Expense Categories
    ========================== --}}
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between config-card-header"
           role="button"
           data-bs-toggle="collapse"
           data-bs-target="#expenseCategoriesCollapse"
           aria-expanded="false"
           aria-controls="expenseCategoriesCollapse">
        <div>
          <strong>Expense Categories</strong>
          <div class="text-muted small">Used on Expense entries</div>
        </div>
        <i class="fas fa-chevron-down config-chevron"></i>
      </div>

      <div id="expenseCategoriesCollapse" class="collapse" data-bs-parent="#configAccordion">
        <div class="card-body">

          {{-- Add new --}}
          <form method="POST" action="{{ route('admin.settings.config.expense_categories.store') }}" class="mb-3">
            @csrf
            <div class="row align-items-end">
              <div class="col-md-5 mb-2">
                <label class="form-label">Name</label>
                <input class="form-control" name="name" required>
              </div>
              <div class="col-md-2 mb-2">
                <label class="form-label">Sort</label>
                <input class="form-control" type="number" name="sort_order" value="0" min="0" max="9999">
              </div>
              <div class="col-md-2 mb-2">
                <div class="form-check mt-4">
                  <input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="cat_active_new">
                  <label class="form-check-label" for="cat_active_new">Active</label>
                </div>
              </div>
              <div class="col-md-3 mb-2">
                <button class="btn btn-primary w-100" type="submit">
                  <i class="fas fa-plus"></i> Add Category
                </button>
              </div>
            </div>
          </form>

          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th style="width:40%">Name</th>
                  <th style="width:15%">Sort</th>
                  <th style="width:15%">Active</th>
                  <th class="text-end" style="width:30%">Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($expenseCategories as $row)
                  <tr>
                    <td>
                      <form method="POST" action="{{ route('admin.settings.config.expense_categories.update', $row) }}" class="d-flex gap-2">
                        @csrf
                        @method('PUT')
                        <input class="form-control form-control-sm" name="name" value="{{ $row->name }}" required>
                    </td>
                    <td>
                        <input class="form-control form-control-sm" type="number" name="sort_order" value="{{ $row->sort_order ?? 0 }}" min="0" max="9999">
                    </td>
                    <td>
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked((int)$row->is_active === 1) id="cat_active_{{ $row->id }}">
                          <label class="form-check-label" for="cat_active_{{ $row->id }}">Yes</label>
                        </div>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" type="submit">
                          <i class="fas fa-save"></i> Save
                        </button>
                      </form>

                      <form method="POST" action="{{ route('admin.settings.config.expense_categories.destroy', $row) }}" class="d-inline"
                            onsubmit="return confirm('Delete this expense category?');">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" type="submit">
                          <i class="fas fa-trash"></i> Delete
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-muted">No expense categories found.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>

    {{-- =========================
         Payment Methods
    ========================== --}}
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between config-card-header"
           role="button"
           data-bs-toggle="collapse"
           data-bs-target="#paymentMethodsCollapse"
           aria-expanded="false"
           aria-controls="paymentMethodsCollapse">
        <div>
          <strong>Payment Methods</strong>
          <div class="text-muted small">Used on Expense entries</div>
        </div>
        <i class="fas fa-chevron-down config-chevron"></i>
      </div>

      <div id="paymentMethodsCollapse" class="collapse" data-bs-parent="#configAccordion">
        <div class="card-body">

          {{-- Add new --}}
          <form method="POST" action="{{ route('admin.settings.config.payment_methods.store') }}" class="mb-3">
            @csrf
            <div class="row align-items-end">
              <div class="col-md-5 mb-2">
                <label class="form-label">Name</label>
                <input class="form-control" name="name" required>
              </div>
              <div class="col-md-2 mb-2">
                <label class="form-label">Sort</label>
                <input class="form-control" type="number" name="sort_order" value="0" min="0" max="9999">
              </div>
              <div class="col-md-2 mb-2">
                <div class="form-check mt-4">
                  <input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="pm_active_new">
                  <label class="form-check-label" for="pm_active_new">Active</label>
                </div>
              </div>
              <div class="col-md-3 mb-2">
                <button class="btn btn-primary w-100" type="submit">
                  <i class="fas fa-plus"></i> Add Method
                </button>
              </div>
            </div>
          </form>

          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th style="width:40%">Name</th>
                  <th style="width:15%">Sort</th>
                  <th style="width:15%">Active</th>
                  <th class="text-end" style="width:30%">Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($paymentMethods as $row)
                  <tr>
                    <td>
                      <form method="POST" action="{{ route('admin.settings.config.payment_methods.update', $row) }}" class="d-flex gap-2">
                        @csrf
                        @method('PUT')
                        <input class="form-control form-control-sm" name="name" value="{{ $row->name }}" required>
                    </td>
                    <td>
                        <input class="form-control form-control-sm" type="number" name="sort_order" value="{{ $row->sort_order ?? 0 }}" min="0" max="9999">
                    </td>
                    <td>
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked((int)$row->is_active === 1) id="pm_active_{{ $row->id }}">
                          <label class="form-check-label" for="pm_active_{{ $row->id }}">Yes</label>
                        </div>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" type="submit">
                          <i class="fas fa-save"></i> Save
                        </button>
                      </form>

                      <form method="POST" action="{{ route('admin.settings.config.payment_methods.destroy', $row) }}" class="d-inline"
                            onsubmit="return confirm('Delete this payment method?');">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" type="submit">
                          <i class="fas fa-trash"></i> Delete
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-muted">No payment methods found.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>

  </div> {{-- /accordion --}}
</div>
@endsection
