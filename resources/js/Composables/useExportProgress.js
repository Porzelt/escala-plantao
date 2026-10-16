import axios from 'axios';
import { onBeforeUnmount, ref } from 'vue';

/**
 * Dispara a exportação (job na fila `exports` do Horizon) e acompanha o
 * progresso por polling até o arquivo ficar pronto.
 */
export function useExportProgress() {
    const state = ref(null); // { status, progress, download_url, error }
    let timer = null;

    const stop = () => {
        clearTimeout(timer);
        timer = null;
    };

    const poll = async (id) => {
        const { data } = await axios.get(route('exports.show', id));
        state.value = data;

        if (data.status === 'concluida') {
            window.location.href = data.download_url;
        } else if (data.status !== 'falhou') {
            timer = setTimeout(() => poll(id), 800);
        }
    };

    const start = async (scheduleId, format) => {
        stop();
        const { data } = await axios.post(route('schedules.export', scheduleId), { format });
        state.value = data;
        poll(data.id);
    };

    onBeforeUnmount(stop);

    return { state, start };
}
