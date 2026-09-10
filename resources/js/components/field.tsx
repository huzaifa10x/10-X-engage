import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { ReactNode } from 'react';

export default function Field({ label, hint, error, children, required }: { label: string; hint?: string; error?: string; children: ReactNode; required?: boolean }) {
    return (
        <div className="space-y-1.5">
            <Label className="text-sm font-medium">
                {label}
                {required && <span className="ml-0.5 text-red-500">*</span>}
            </Label>
            {children}
            {hint && !error && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}
