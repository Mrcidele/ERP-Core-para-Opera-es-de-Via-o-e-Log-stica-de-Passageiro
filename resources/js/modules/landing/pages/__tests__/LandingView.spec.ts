import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import LandingView from '../LandingView.vue';

async function mountPage() {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/apresentacao', name: 'landing', component: LandingView },
            { path: '/login', name: 'login', component: { template: '<div />' } },
        ],
    });

    await router.push('/apresentacao');

    return mount(LandingView, { global: { plugins: [router] } });
}

describe('LandingView', () => {
    it('presents the four controls with a real screen and a description of each', async () => {
        const features = (await mountPage()).findAll('[data-test="feature"]');

        expect(features.map((feature) => feature.get('h3').text())).toEqual([
            'Diesel por caminhão, comparado com a meta dele',
            'Escala que respeita a oficina e o descanso do motorista',
            'Frota organizada por tipo, carga útil e revisão',
            'Auditoria que ninguém apaga',
        ]);

        for (const feature of features) {
            expect(feature.get('img').attributes('src')).toMatch(/^\/media\/tela-.+\.jpg$/);
            expect(feature.get('img').attributes('alt')).not.toBe('');
        }
    });

    it('plays the demo video only when asked', async () => {
        const video = (await mountPage()).get('[data-test="demo-video"]');

        expect(video.attributes('src')).toBe('/media/erp-frotacarga-demo.mp4');
        expect(video.attributes('preload')).toBe('none');
        expect(video.attributes('poster')).toBe('/media/demo-capa.jpg');
    });

    it('books a demo by WhatsApp or e-mail with the message already written', async () => {
        const wrapper = await mountPage();

        const whatsappLinks = wrapper.findAll('[data-test="cta-whatsapp"]');

        expect(whatsappLinks).toHaveLength(2);
        for (const link of whatsappLinks) {
            expect(link.attributes('href')).toBe(
                'https://wa.me/5543996280596?text=Ol%C3%A1%20Caio%2C%20vi%20a%20p%C3%A1gina%20do%20ERP%20FrotaCarga%20e%20quero%20saber%20mais%20sobre%20o%20piloto%20de%2030%20dias.',
            );
        }
        expect(wrapper.get('[data-test="cta-email"]').attributes('href')).toBe(
            'mailto:caiomarcidele@gmail.com?subject=Demonstra%C3%A7%C3%A3o%20do%20ERP%20FrotaCarga',
        );
    });

    it('says what the system does not do, starting with fiscal documents', async () => {
        const items = (await mountPage()).findAll('[data-test="not-included"] li');

        expect(items).toHaveLength(3);
        expect(items[0]!.text()).toContain('CT-e, MDF-e e CIOT');
    });

    it('leads to the sign-in for who already uses the system', async () => {
        expect((await mountPage()).get('[data-test="sign-in"]').attributes('href')).toBe('/login');
    });
});
