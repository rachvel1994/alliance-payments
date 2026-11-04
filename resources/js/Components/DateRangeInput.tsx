import { useId } from 'react';
import InputError from './InputError';

type Props = {
    from: string;
    to: string;
    onFromChange: (value: string) => void;
    onToChange: (value: string) => void;
    errors: { date_from?: string; as_of?: string };
};

export default function DateRangeInput({ from, to, onFromChange, onToChange, errors }: Props) {
    const id = useId();
    const inputClass =
        'min-h-11 w-full min-w-0 rounded-lg border-gray-300 text-sm font-normal focus:border-brand focus:ring-brand';

    return (
        <div
            role="group"
            aria-label="საანგარიშო პერიოდი"
            className="grid w-full max-w-full grid-cols-1 gap-3 sm:w-auto sm:grid-cols-2"
        >
            <div className="min-w-0 flex-1">
                <label htmlFor={`${id}-from`} className="mb-1.5 block text-sm font-medium text-gray-700">
                    დაწყების თარიღი
                </label>
                <input
                    id={`${id}-from`}
                    type="date"
                    value={from}
                    max={to || undefined}
                    onChange={(event) => onFromChange(event.target.value)}
                    className={inputClass}
                    aria-invalid={Boolean(errors.date_from)}
                    aria-describedby={errors.date_from ? `${id}-from-error` : undefined}
                />
                <InputError id={`${id}-from-error`} message={errors.date_from} />
            </div>
            <div className="min-w-0 flex-1">
                <label htmlFor={`${id}-to`} className="mb-1.5 block text-sm font-medium text-gray-700">
                    დასრულების თარიღი
                </label>
                <input
                    id={`${id}-to`}
                    type="date"
                    required
                    value={to}
                    min={from || undefined}
                    onChange={(event) => onToChange(event.target.value)}
                    className={inputClass}
                    aria-invalid={Boolean(errors.as_of)}
                    aria-describedby={errors.as_of ? `${id}-to-error` : undefined}
                />
                <InputError id={`${id}-to-error`} message={errors.as_of} />
            </div>
        </div>
    );
}
