<script setup lang="ts">
import AppIcon from '@/core/ui/AppIcon.vue';
import StatusBadge from '@/core/ui/StatusBadge.vue';
import { userStatus, type ManagedUser } from '../types/user';

defineProps<{ users: ManagedUser[]; currentUserId: string | null }>();
const emit = defineEmits<{ edit: [user: ManagedUser]; toggle: [user: ManagedUser] }>();
</script>

<template>
    <div class="overflow-x-auto">
        <table class="data-table min-w-[720px]">
            <thead>
                <tr>
                    <th scope="col">Nome</th>
                    <th scope="col">E-mail</th>
                    <th scope="col">Perfil</th>
                    <th scope="col">Situação</th>
                    <th scope="col"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="user in users" :key="user.id" data-test="user-row" :class="{ 'text-ink-400': !user.active }">
                    <td class="font-medium text-ink-900">
                        {{ user.name }}
                        <span v-if="user.id === currentUserId" class="ml-1 text-xs font-normal text-ink-500" data-test="you">(você)</span>
                    </td>
                    <td class="text-ink-600">{{ user.email }}</td>
                    <td class="whitespace-nowrap">{{ user.role_label }}</td>
                    <td><StatusBadge kind="user" :status="userStatus(user)" /></td>
                    <td>
                        <div class="flex justify-end gap-1">
                            <button type="button" class="icon-btn" data-test="edit" :aria-label="`Editar ${user.name}`" @click="emit('edit', user)">
                                <AppIcon name="pencil" />
                            </button>
                            <!-- O próprio acesso não se desativa: a API recusa, e o botão nem aparece. -->
                            <button
                                v-if="user.id !== currentUserId"
                                type="button"
                                class="icon-btn"
                                :class="user.active ? 'hover:!bg-brake-50 hover:!text-brake-700' : 'hover:!bg-road-50 hover:!text-road-700'"
                                data-test="toggle"
                                :aria-label="user.active ? `Desativar ${user.name}` : `Reativar ${user.name}`"
                                @click="emit('toggle', user)"
                            >
                                <AppIcon :name="user.active ? 'ban' : 'undo'" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
