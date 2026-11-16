import { mount, type VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { toDateTimeInput } from '@/core/utils/datetime';
import type { ScheduleTimeline, TimelineTrip } from '../../types/timeline';
import ScheduleGrid from '../ScheduleGrid.vue';

// Grade de 40 horas, para as posições serem números redondos: cada hora vale 2,5% da faixa.
const START = new Date('2026-11-20T00:00:00Z');
const at = (hours: number): string => new Date(START.getTime() + hours * 3_600_000).toISOString();

const trip = (id: string, from: number, to: number, status: TimelineTrip['status'] = 'scheduled'): TimelineTrip => ({
    id,
    status,
    departure_time: at(from),
    arrival_time: at(to),
    rest_until: at(to + 11),
    line: { origin: 'Curitiba', destination: 'Ourinhos' },
    vehicle: { id: 'bus-1', plate: 'ABC1D23' },
    driver: { id: 'driver-1', name: 'Ana Lima' },
});

const timeline = (overrides: Partial<ScheduleTimeline> = {}): ScheduleTimeline => ({
    from: at(-3),
    to: at(40),
    driver_rest_hours: 11,
    vehicles: [
        { id: 'bus-1', plate: 'ABC1D23', status: 'active', trips: [trip('t1', 8, 14)], maintenance_orders: [] },
        { id: 'bus-2', plate: 'XYZ9K88', status: 'maintenance', trips: [], maintenance_orders: [{ id: 'o1', type: 'corrective', status: 'open', opened_at: at(-50) }] },
    ],
    drivers: [
        { id: 'driver-1', name: 'Ana Lima', trips: [trip('t1', 8, 14)] },
        { id: 'driver-2', name: 'Bruno Dias', trips: [] },
    ],
    ...overrides,
});

const grid = (data: ScheduleTimeline = timeline(), canSchedule = true, now: Date = new Date(at(4))): VueWrapper =>
    mount(ScheduleGrid, { props: { timeline: data, start: START, now, canSchedule } });

const style = (wrapper: { attributes: (name: string) => string | undefined }): string => wrapper.attributes('style') ?? '';

/** Clique em um ponto da faixa, dado como fração da largura. */
async function clickTrack(wrapper: VueWrapper, rowHook: string, index: number, ratio: number): Promise<void> {
    const track = wrapper.findAll(`[data-test="${rowHook}"]`)[index]!.get('[data-test="track"]');

    track.element.getBoundingClientRect = () => ({ left: 100, width: 1000, top: 0, right: 1100, bottom: 48, height: 48, x: 100, y: 0, toJSON: () => ({}) });
    await track.trigger('click', { clientX: 100 + ratio * 1000 });
}

describe('ScheduleGrid', () => {
    it('draws one row per vehicle and per driver', () => {
        const wrapper = grid();

        expect(wrapper.findAll('[data-test="vehicle-row"]').map((row) => row.get('.plate').text())).toEqual(['ABC1D23', 'XYZ9K88']);
        expect(wrapper.findAll('[data-test="driver-row"]').map((row) => row.text())).toEqual(expect.arrayContaining([expect.stringContaining('Ana Lima'), expect.stringContaining('Bruno Dias')]));
    });

    it('places a trip in proportion to its time', () => {
        const block = grid().findAll('[data-test="vehicle-row"]')[0]!.get('[data-test="block-trip"]');

        // Das 8h às 14h de uma grade de 40 horas: começa em 20% e ocupa 15%.
        expect(style(block)).toContain('left: 20%');
        expect(style(block)).toContain('width: 15%');
        expect(block.text()).toBe('Curitiba → Ourinhos');
        expect(block.attributes('title')).toContain('Motorista: Ana Lima');
        expect(block.attributes('title')).toContain('Agendada');
    });

    it('draws the 11 hours of rest after each trip of a driver, and not on the vehicle', () => {
        const wrapper = grid();
        const driver = wrapper.findAll('[data-test="driver-row"]')[0]!;
        const rest = driver.get('[data-test="block-rest"]');

        // Das 14h à 1h do dia seguinte: 11 horas = 27,5% da grade.
        expect(style(rest)).toContain('left: 35%');
        expect(style(rest)).toContain('width: 27.5%');
        expect(rest.text()).toBe('Descanso');
        expect(rest.attributes('title')).toContain('Descanso obrigatório de 11 horas');
        expect(driver.get('[data-test="block-trip"]').attributes('title')).toContain('Veículo: ABC1D23');
        expect(wrapper.findAll('[data-test="vehicle-row"]')[0]!.find('[data-test="block-rest"]').exists()).toBe(false);
    });

    it('draws a pending maintenance order from its opening to the end of the grid', () => {
        const order = grid().findAll('[data-test="vehicle-row"]')[1]!.get('[data-test="block-maintenance"]');

        // Aberta antes do início da grade: ocupa a faixa inteira.
        expect(style(order)).toContain('left: 0%');
        expect(style(order)).toContain('width: 100%');
        expect(order.text()).toBe('Oficina: corretiva');
    });

    it('clips what starts before the grid and drops what is entirely outside', () => {
        const wrapper = grid(
            timeline({
                vehicles: [{ id: 'bus-1', plate: 'ABC1D23', status: 'active', trips: [trip('early', -2, 2, 'in_progress'), trip('late', 38, 46)], maintenance_orders: [] }],
                drivers: [{ id: 'driver-1', name: 'Ana Lima', trips: [trip('old', -16, -8, 'completed')] }],
            }),
        );
        const blocks = wrapper.findAll('[data-test="vehicle-row"] [data-test="block-trip"]');

        expect(style(blocks[0]!)).toContain('left: 0%');
        expect(style(blocks[0]!)).toContain('width: 5%');
        expect(blocks[0]!.classes()).toContain('bg-signal-500');
        expect(style(blocks[1]!)).toContain('left: 95%');
        expect(style(blocks[1]!)).toContain('width: 5%');
        // A viagem antiga não aparece, mas o descanso dela (até as 3h) ainda alcança o começo da grade.
        expect(wrapper.find('[data-test="driver-row"] [data-test="block-trip"]').exists()).toBe(false);
        expect(style(wrapper.get('[data-test="driver-row"] [data-test="block-rest"]'))).toContain('width: 7.5%');
    });

    it('marks every three hours and the current instant', () => {
        const wrapper = grid();

        // 40 horas: marcas em 0, 3, ..., 39.
        expect(wrapper.findAll('[data-test="time-axis"] [data-test="tick"]')).toHaveLength(14);
        expect(style(wrapper.findAll('[data-test="time-axis"] [data-test="tick"]')[1]!)).toContain('left: 7.5%');
        expect(style(wrapper.get('[data-test="now"]'))).toContain('left: 10%');
        expect(style(wrapper.get('[data-test="past"]'))).toContain('width: 10%');
    });

    it('has no current-instant mark when the grid is of another day', () => {
        expect(grid(timeline(), true, new Date(at(-30))).find('[data-test="now"]').exists()).toBe(false);
        expect(style(grid(timeline(), true, new Date(at(-30))).get('[data-test="past"]'))).toContain('width: 0%');
    });

    it('opens the scheduling with the vehicle and the half hour that was clicked', async () => {
        const wrapper = grid();

        // 51,9% de 40 horas = 20h45: arredonda para baixo, 20h30.
        await clickTrack(wrapper, 'vehicle-row', 0, 0.519);

        expect(wrapper.emitted('schedule')).toEqual([[{ vehicle_id: 'bus-1', departure_time: toDateTimeInput(new Date(at(20.5))) }]]);
    });

    it('opens the scheduling with the driver whose row was clicked', async () => {
        const wrapper = grid();

        await clickTrack(wrapper, 'driver-row', 1, 0.75);

        expect(wrapper.emitted('schedule')).toEqual([[{ driver_id: 'driver-2', departure_time: toDateTimeInput(new Date(at(30))) }]]);
    });

    it('ignores a click on a time that has already passed, on a block or on a vehicle in the workshop', async () => {
        const wrapper = grid();

        await clickTrack(wrapper, 'vehicle-row', 0, 0.05);
        await wrapper.get('[data-test="block-trip"]').trigger('click');
        await wrapper.get('[data-test="block-rest"]').trigger('click');
        await clickTrack(wrapper, 'vehicle-row', 1, 0.75);

        expect(wrapper.emitted('schedule')).toBeUndefined();
    });

    it('offers a button per row to schedule without picking a time, except for a vehicle in the workshop', async () => {
        const wrapper = grid();
        const buttons = wrapper.findAll('[data-test="schedule-row"]');

        expect(buttons.map((button) => button.attributes('aria-label'))).toEqual([
            'Agendar viagem com o veículo ABC1D23',
            'Agendar viagem com Ana Lima',
            'Agendar viagem com Bruno Dias',
        ]);

        await buttons[0]!.trigger('click');
        await buttons[2]!.trigger('click');

        expect(wrapper.emitted('schedule')).toEqual([[{ vehicle_id: 'bus-1' }], [{ driver_id: 'driver-2' }]]);
    });

    it('is read-only without permission to schedule', async () => {
        const wrapper = grid(timeline(), false);

        await clickTrack(wrapper, 'vehicle-row', 0, 0.75);

        expect(wrapper.find('[data-test="schedule-row"]').exists()).toBe(false);
        expect(wrapper.emitted('schedule')).toBeUndefined();
        expect(wrapper.findAll('[data-test="block-trip"]')).toHaveLength(2);
    });

    it('says when there is no active vehicle or driver', () => {
        const wrapper = grid(timeline({ vehicles: [], drivers: [] }));

        expect(wrapper.get('[data-test="no-vehicles"]').text()).toBe('Nenhum veículo ativo.');
        expect(wrapper.get('[data-test="no-drivers"]').text()).toBe('Nenhum motorista ativo.');
    });
});
