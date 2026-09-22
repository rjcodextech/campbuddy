<x-app-layout title="Add event">
    <div class="max-w-3xl">
        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line p-6">
            <form method="POST" action="{{ route('admin.events.store') }}">
                @csrf
                @include('admin.events._form')

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-maroon text-white text-sm font-medium rounded-md hover:bg-maroon-dark">
                        Create event
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
