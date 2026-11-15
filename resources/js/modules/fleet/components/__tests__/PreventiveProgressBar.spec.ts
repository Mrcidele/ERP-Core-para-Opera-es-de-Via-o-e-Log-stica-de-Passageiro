import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { preventiveProgress } from '../../types/vehicle';
import PreventiveProgressBar from '../PreventiveProgressBar.vue';

const vehicle = (current: number, next: number | null) => ({ current_mileage: current, next_preventive_maintenance_km: next });

describe('preventiveProgress', () => {
    it('is null when no maintenance is scheduled', () => {
        expect(preventiveProgress(vehicle(100_000, null))).toBeNull();
    });

    it.each([
        ['just serviced', 100_000, 120_000, { remainingKm: 20_000, percent: 0, due: false, near: false }],
        ['halfway', 110_000, 120_000, { remainingKm: 10_000, percent: 50, due: false, near: false }],
        ['close to the limit', 118_500, 120_000, { remainingKm: 1_500, percent: 93, due: false, near: true }],
        ['reached exactly', 120_000, 120_000, { remainingKm: 0, percent: 100, due: true, near: false }],
        ['overdue', 121_500, 120_000, { remainingKm: -1_500, percent: 100, due: true, near: false }],
        ['scheduled further than one interval', 100_000, 160_000, { remainingKm: 60_000, percent: 0, due: false, near: false }],
    ])('%s', (_label, current, next, expected) => {
        expect(preventiveProgress(vehicle(current, next))).toEqual(expected);
    });
});

describe('PreventiveProgressBar', () => {
    it('says when nothing is scheduled', () => {
        const wrapper = mount(PreventiveProgressBar, { props: { vehicle: vehicle(100_000, null) } });

        expect(wrapper.get('[data-test="preventive-none"]').text()).toBe('Não programada');
    });

    it('shows how many km are left', () => {
        const wrapper = mount(PreventiveProgressBar, { props: { vehicle: vehicle(110_000, 120_000) } });

        expect(wrapper.get('[data-test="preventive-progress"]').attributes('data-state')).toBe('ok');
        expect(wrapper.text()).toBe('Faltam 10.000 km');
        expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('50');
    });

    it('warns when the maintenance is near', () => {
        expect(mount(PreventiveProgressBar, { props: { vehicle: vehicle(118_500, 120_000) } }).get('[data-test="preventive-progress"]').attributes('data-state')).toBe('near');
    });

    it('shows by how many km the maintenance is overdue', () => {
        const wrapper = mount(PreventiveProgressBar, { props: { vehicle: vehicle(121_500, 120_000) } });

        expect(wrapper.get('[data-test="preventive-progress"]').attributes('data-state')).toBe('due');
        expect(wrapper.text()).toBe('Vencida há 1.500 km');
    });
});
