import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
    Listbox,
    ListboxButton,
    ListboxOption,
    ListboxOptions,
} from '@headlessui/react';
import { useId, useState } from 'react';

export type SelectOption = { value: string; label: string; description?: string };

const panel =
    'z-[60] max-h-72 max-w-[calc(100vw-1rem)] overflow-y-auto overscroll-contain rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg shadow-ink/10 [--anchor-gap:8px] [--anchor-padding:8px] transition duration-100 ease-out data-[closed]:opacity-0 motion-reduce:transition-none focus:outline-none';
const optionStyle =
    'group flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-ink outline-none data-[focus]:bg-gray-100/80 data-[selected]:bg-brand-soft data-[selected]:text-brand';

function OptionContent({ option }: { option: SelectOption }) {
    return (
        <>
            <span className="min-w-0 flex-1 break-words">
                <span className="block group-data-[selected]:font-semibold">{option.label}</span>
                {option.description && (
                    <span className="mt-0.5 block text-xs leading-5 text-gray-500">{option.description}</span>
                )}
            </span>
            <svg
                aria-hidden="true"
                className="invisible h-4 w-4 shrink-0 text-brand group-data-[selected]:visible"
                viewBox="0 0 20 20"
                fill="none"
                stroke="currentColor"
                strokeWidth={1.8}
                strokeLinecap="round"
                strokeLinejoin="round"
            >
                <path d="m4 10 4 4 8-8" />
            </svg>
        </>
    );
}

function Chevron() {
    return (
        <svg
            aria-hidden="true"
            className="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-100 group-data-[open]:rotate-180 group-data-[open]:text-brand motion-reduce:transition-none"
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.5}
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="m6 8 4 4 4-4" />
        </svg>
    );
}

export default function SelectInput({
    label,
    value,
    onChange,
    options,
    placeholder,
    searchable = false,
    required = false,
    disabled = false,
    error,
    className = '',
    id,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: SelectOption[];
    placeholder?: string;
    searchable?: boolean;
    required?: boolean;
    disabled?: boolean;
    error?: string;
    className?: string;
    id?: string;
}) {
    const generatedId = useId();
    const fieldId = id ?? generatedId;
    const [query, setQuery] = useState('');
    const choices =
        !searchable && placeholder !== undefined && !options.some((option) => option.value === '')
            ? [{ value: '', label: placeholder }, ...options]
            : options;
    const selected = choices.find((option) => option.value === value) ?? null;
    const showHint = Boolean(searchable && selected?.description && !query && !error);
    const describedBy = error ? `${fieldId}-error` : showHint ? `${fieldId}-hint` : undefined;
    const term = query.trim().toLocaleLowerCase();
    const filtered = options.filter((option) =>
        `${option.label} ${option.description ?? ''}`.toLocaleLowerCase().includes(term),
    );
    const control = `min-h-11 w-full rounded-lg border bg-white py-2.5 pl-3.5 text-sm text-ink shadow-sm shadow-gray-100 transition-colors duration-100 focus-visible:ring-2 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-50 disabled:text-gray-400 disabled:shadow-none motion-reduce:transition-none ${error ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 hover:border-gray-400 focus:border-brand focus:ring-brand data-[open]:border-brand data-[open]:ring-2 data-[open]:ring-brand/10'}`;
    const choose = (option: SelectOption | null) => {
        onChange(option?.value ?? '');
        setQuery('');
    };

    return (
        <div className={`min-w-0 ${className}`}>
            <label id={`${fieldId}-label`} htmlFor={fieldId} className="mb-1.5 block text-sm font-medium text-gray-700">
                {label}
            </label>
            {searchable ? (
                <Combobox
                    value={selected}
                    by="value"
                    disabled={disabled}
                    immediate
                    onClose={() => setQuery('')}
                    onChange={choose}
                >
                    <div className="relative">
                        <ComboboxInput
                            id={fieldId}
                            className={`block pr-11 ${control}`}
                            aria-labelledby={`${fieldId}-label`}
                            aria-invalid={Boolean(error)}
                            aria-describedby={describedBy}
                            required={required}
                            placeholder={placeholder ?? 'მოძებნე და აირჩიე'}
                            displayValue={(option: SelectOption | null) => option?.label ?? ''}
                            onChange={(event) => setQuery(event.target.value)}
                            onFocus={(event) => event.currentTarget.select()}
                        />
                        <ComboboxButton
                            aria-label={`${label} — არჩევა`}
                            className="group absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand"
                        >
                            <Chevron />
                        </ComboboxButton>
                    </div>
                    <ComboboxOptions anchor="bottom start" transition className={`${panel} w-[var(--input-width)]`}>
                        {filtered.length === 0 && (
                            <p role="status" className="px-3 py-5 text-sm text-gray-500">
                                ჩანაწერი ვერ მოიძებნა.
                            </p>
                        )}
                        {filtered.map((option) => (
                            <ComboboxOption key={option.value} value={option} className={optionStyle}>
                                <OptionContent option={option} />
                            </ComboboxOption>
                        ))}
                    </ComboboxOptions>
                </Combobox>
            ) : (
                <Listbox value={selected} by="value" disabled={disabled} invalid={Boolean(error)} onChange={choose}>
                    <ListboxButton
                        id={fieldId}
                        type="button"
                        aria-labelledby={`${fieldId}-label ${fieldId}-value`}
                        aria-invalid={Boolean(error)}
                        aria-describedby={describedBy}
                        className={`group flex items-center justify-between gap-3 pr-3 text-left focus:outline-none ${control}`}
                    >
                        <span id={`${fieldId}-value`} className={`min-w-0 truncate ${selected ? '' : 'text-gray-400'}`}>
                            {selected?.label ?? placeholder ?? 'აირჩიე'}
                        </span>
                        <Chevron />
                    </ListboxButton>
                    <ListboxOptions
                        anchor="bottom start"
                        transition
                        aria-required={required || undefined}
                        className={`${panel} w-[var(--button-width)]`}
                    >
                        {choices.length === 0 && (
                            <p role="status" className="px-3 py-5 text-sm text-gray-500">
                                არჩევანი არ არის.
                            </p>
                        )}
                        {choices.map((option) => (
                            <ListboxOption key={option.value} value={option} className={optionStyle}>
                                <OptionContent option={option} />
                            </ListboxOption>
                        ))}
                    </ListboxOptions>
                </Listbox>
            )}
            {showHint && (
                <p id={`${fieldId}-hint`} className="mt-1.5 text-xs leading-5 text-gray-500">
                    {selected?.description}
                </p>
            )}
            {error && (
                <p id={`${fieldId}-error`} role="alert" className="mt-1.5 text-sm text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}
