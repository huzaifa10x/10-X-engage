import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Reads flash messages shared from HandleInertiaRequests and exposes a dismissible state. */
export function useFlash() {
    const { flash } = usePage<SharedData>().props;
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        setVisible(true);
    }, [flash?.success, flash?.error, flash?.warning]);

    return { flash: visible ? flash : undefined, dismiss: () => setVisible(false) };
}
