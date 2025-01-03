<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import {ref, h} from "vue";
import Network from '@/Pages/Structure/Partials/Network.vue';
import Listing from '@/Pages/Structure/Partials/Listing.vue';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import { wTrans } from 'laravel-vue-i18n';
import Empty from '@/Components/Empty.vue';

const props = defineProps({
    tab: Number,
})

const user = usePage().props.auth.user;
const showEmpty = ref(false); // State to control the visibility of Empty component

const tabs = ref([
        {
            title: wTrans('public.network'),
            component: h(Network)
        },
        {
            title: wTrans('public.listing'),
            component: h(Listing),
        }
]);

const activeIndex = ref(props.tab);
// Function to handle the noData event from the Network component
const handleNoData = () => {
  console.log('Handling noData event');
  showEmpty.value = true;
};

</script>

<template>
    <AuthenticatedLayout :title="$t('public.structure')">
        <div v-if="!showEmpty">
            <TabView
                v-if="user.role === 'ib'"
                v-model:activeIndex="activeIndex"
                class="flex flex-col gap-5 self-stretch"
            >
                <TabPanel v-for="(tab, index) in tabs" :key="index" :header="tab.title">
                    <component
                        v-if="index === 0" 
                        :is="tab.component" 
                        @noData="handleNoData" 
                    />
                    <!-- Render Listing component -->
                    <component 
                        v-else
                        :is="tab.component" 
                    />
                </TabPanel>
            </TabView>

            <Network
                v-else
                @noData="handleNoData"
            />
        </div>
        <Empty v-else :title="$t('public.empty_downline_title')" :message="$t('public.empty_downline_message')">
            <template #image>
                <img src="/img/no_data/empty_downline.svg"  alt="" class="w-60 h-[180px]">
            </template>
        </Empty>
    </AuthenticatedLayout>
</template>
