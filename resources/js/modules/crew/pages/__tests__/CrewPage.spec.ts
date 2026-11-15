import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import { signInAs } from '@/tests/auth';
import CrewPage from '../CrewPage.vue';

describe('CrewPage', () => {
    beforeEach(() => setActivePinia(createPinia()));

    it('lets a role with crew.manage open the registration form', async () => {
        signInAs('admin');
        const wrapper = mount(CrewPage);

        await wrapper.get('[data-test="new-employee"]').trigger('click');

        expect(wrapper.find('[data-test="cpf"]').exists()).toBe(true);
    });

    it.each(['dispatcher', 'fleet_manager'] as const)('is read-only for %s, who only has crew.view', (role) => {
        signInAs(role);
        const wrapper = mount(CrewPage);

        expect(wrapper.find('[data-test="new-employee"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="read-only"]').exists()).toBe(true);
    });
});
