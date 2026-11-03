import Icon from '@/Components/Icon';
import { forwardRef, InputHTMLAttributes, useState } from 'react';

export default forwardRef<HTMLInputElement, Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & { id: string }>(
    function PasswordInput({ id, className = '', disabled, ...props }, ref) {
        const [visible, setVisible] = useState(false);

        return (
            <div className="relative">
                <input
                    {...props}
                    id={id}
                    ref={ref}
                    type={visible ? 'text' : 'password'}
                    disabled={disabled}
                    className={`min-h-11 w-full rounded-lg border-gray-300 pr-12 shadow-none focus:border-brand focus:ring-brand ${className}`}
                />
                <button
                    type="button"
                    disabled={disabled}
                    onClick={() => setVisible(!visible)}
                    aria-label="პაროლის ჩვენება"
                    aria-pressed={visible}
                    aria-controls={id}
                    className="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-gray-500 hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand disabled:opacity-50"
                >
                    <Icon name={visible ? 'eyeOff' : 'eye'} />
                </button>
            </div>
        );
    },
);
