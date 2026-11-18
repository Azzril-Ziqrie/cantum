@php($assigned = $user ? $user->branches->pluck('id')->all() : (array) old('branch_ids', []))
<div class="adm-grid adm-grid--wide">
  <label class="adm-field"><span class="label">Nama</span><input type="text" name="name" value="{{ $user->name ?? old('name') }}" required maxlength="120"></label>
  <label class="adm-field"><span class="label">Email</span><input type="email" name="email" value="{{ $user->email ?? old('email') }}" required maxlength="190" autocomplete="off"></label>
  <label class="adm-field"><span class="label">Role</span>
    <select name="role" required>
      @foreach ($roleLabels as $value => $label)
        <option value="{{ $value }}" @selected(($user->role->value ?? old('role', 'cashier')) === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </label>
</div>
<fieldset class="stack" style="border:0">
  <legend class="label">Akses cabang (owner otomatis semua cabang)</legend>
  <div class="adm-grid">
    @foreach ($branches as $branch)
      <label class="adm-check"><input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, $assigned))>{{ $branch->name }}</label>
    @endforeach
  </div>
</fieldset>
