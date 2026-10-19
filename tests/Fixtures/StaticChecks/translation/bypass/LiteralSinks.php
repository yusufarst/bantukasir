<?php

namespace Tests\Fixtures\StaticChecks;

use App\Support\Validation\CostRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

// Negative samples of the literal check from the class-A adversarial review of
// TASK-003, finding A-07: user-facing literals in the server sinks that the
// check did not read. A line tagged K is reported; a line tagged C is a
// control and is not. Never executed.

final class LiteralSinks
{
    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.required' => 'Jumlah wajib diisi.', // K01 FormRequest::messages()
            'amount.min' => __('sample.greeting.title'), // C01 a message from a key
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['amount' => 'jumlah']; // K02 FormRequest::attributes(), the names in messages
    }

    public function sinks(ValidatorContract $validator, Request $request, mixed $query, mixed $items): void
    {
        Inertia::render('Page/Name', ['title' => 'Judul halaman']); // K03 an Inertia prop
        Inertia::render('Page/Name', ['nested' => ['label' => 'Label bersarang']]); // K04 a nested Inertia prop
        inertia('Page/Name', ['title' => 'Judul lain']); // K05 a prop through inertia()
        back()->with('status', 'Data tersimpan.'); // K06 a flash message through with()
        redirect()->route('home')->with(['status' => 'Data tersimpan.']); // K07 a flash message through with([])
        session()->flash('status', 'Data tersimpan.'); // K08 a flash message through flash()
        Session::flash('status', 'Data tersimpan.'); // K09 a flash message through the facade
        response()->json(['message' => 'Gagal menyimpan.'], 422); // K10 a JSON message
        response()->json('Gagal menyimpan.'); // K11 a JSON text
        $validator->errors()->add('amount', 'Jumlah salah.'); // K12 an error added after validation
        Validator::make([], [], ['required' => 'Wajib diisi.']); // K13 custom messages of Validator::make()
        Validator::make([], [], [], ['amount' => 'jumlah']); // K14 custom attributes of Validator::make()
        validator([], [], ['required' => 'Wajib diisi.']); // K15 custom messages of validator()
        $request->validate(['amount' => 'required'], ['required' => 'Wajib diisi.']); // K16 custom messages of validate()
        new CostRule('Harga pokok belum benar.', __('sample.greeting.title')); // K17 the messages of CostRule
        new CostRule(__('sample.greeting.title'), 'Harga pokok terlalu besar.'); // K18 the messages of CostRule
        Inertia::render('Page/Name', ['amount' => $items, 'title' => __('sample.greeting.title')]); // C02 a component name, a value and a key
        back()->with('status', __('sample.greeting.title')); // C03 a flash message from a key
        $query->with('items'); // C04 Eloquent's with() names a relation
        $query->with(['items', 'lines']); // C05 relations without messages
        response()->json(['amount' => $items, 'status' => __('sample.greeting.title')]); // C06 a value and a key
        new CostRule(__('sample.greeting.title'), __('sample.greeting.title')); // C07 the messages of CostRule from keys
        Validator::make([], ['amount' => 'required|string']); // C08 rules are not messages
    }
}
