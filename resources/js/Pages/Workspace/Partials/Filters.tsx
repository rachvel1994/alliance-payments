import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import { WorkspaceSearch } from '@/types/workspace';
import { InertiaFormProps } from '@inertiajs/react';
import { FormEvent } from 'react';
import { importOptions, sorting } from '../config';

type Props = { section: string; search: InertiaFormProps<WorkspaceSearch> };

export default function Filters({ section, search }: Props) {
    const sortOptions = sorting[section] ?? sorting.contracts;
    const detail = ['contract', 'payment', 'import'].includes(section);

    function handleSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        search.get(window.location.pathname, { preserveState: true });
    }

    return (
        <>
            <form
                onSubmit={handleSearch}
                className="flex flex-wrap items-end gap-4 rounded-xl border border-gray-200 bg-white p-5"
            >
                {!detail && (
                    <label className="flex min-w-0 flex-1 flex-col gap-1.5 text-sm font-medium text-gray-700">
                        ძიება
                        <input
                            type="search"
                            maxLength={100}
                            className="min-h-11 min-w-48 rounded-lg border-gray-300 font-normal focus:border-brand focus:ring-brand"
                            placeholder={section === 'imports' ? 'ფაილის სახელი' : 'სახელი, პირადი ნომერი ან ნომერი'}
                            value={search.data.q}
                            onChange={(event) => search.setData('q', event.target.value)}
                        />
                    </label>
                )}
                {['overdue', 'contract', 'payment'].includes(section) && (
                    <label className="flex flex-col gap-1.5 text-sm font-medium text-gray-700">
                        საანგარიშო თარიღი
                        <input
                            type="date"
                            className="min-h-11 rounded-lg border-gray-300 text-sm font-normal focus:border-brand focus:ring-brand"
                            value={search.data.as_of}
                            onChange={(event) => search.setData('as_of', event.target.value)}
                        />
                    </label>
                )}
                {section === 'apartments' && (
                    <SelectInput
                        className="w-full sm:w-44"
                        label="ბინის სტატუსი"
                        value={search.data.status}
                        onChange={(value) => search.setData('status', value)}
                        placeholder="ყველა სტატუსი"
                        options={[
                            { value: 'free', label: 'თავისუფალი' },
                            { value: 'sold', label: 'გაყიდული' },
                        ]}
                        error={search.errors.status}
                    />
                )}
                {section === 'imports' && (
                    <SelectInput
                        className="w-full sm:w-52"
                        label="იმპორტის სტატუსი"
                        value={search.data.import_status}
                        onChange={(value) => search.setData('import_status', value)}
                        placeholder="ყველა სტატუსი"
                        options={importOptions}
                        error={search.errors.import_status}
                    />
                )}
                {section === 'payments' && (
                    <SelectInput
                        className="w-full sm:w-48"
                        label="კლიენტთან კავშირი"
                        value={search.data.client_match}
                        onChange={(value) => search.setData('client_match', value)}
                        placeholder="ყველა გადახდა"
                        options={[
                            { value: 'matched', label: 'დაკავშირებული' },
                            { value: 'unmatched', label: 'დაუკავშირებელი' },
                        ]}
                        error={search.errors.client_match}
                    />
                )}
                <SelectInput
                    className="w-full sm:w-44"
                    label="დალაგება"
                    value={search.data.sort}
                    onChange={(value) => search.setData('sort', value)}
                    options={sortOptions}
                    error={search.errors.sort}
                />
                <SelectInput
                    className="w-full sm:w-40"
                    label="მიმართულება"
                    value={search.data.direction}
                    onChange={(value) => search.setData('direction', value)}
                    options={[
                        { value: 'asc', label: 'ზრდადობით' },
                        { value: 'desc', label: 'კლებადობით' },
                    ]}
                    error={search.errors.direction}
                />
                <PrimaryButton disabled={search.processing} className="min-h-11">
                    {search.processing ? 'იტვირთება…' : 'ძებნა'}
                </PrimaryButton>
            </form>
            {Object.entries(search.errors)
                .filter(([key]) => !['sort', 'direction', 'status', 'import_status', 'client_match'].includes(key))
                .map(([key, error]) => (
                    <p key={key} role="alert" className="text-sm text-red-600">
                        {error}
                    </p>
                ))}
        </>
    );
}
