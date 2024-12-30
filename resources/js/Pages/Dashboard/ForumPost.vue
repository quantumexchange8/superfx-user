<script setup>
import {ref, watchEffect} from "vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import dayjs from "dayjs";
import Image from 'primevue/image';
import {wTrans} from "laravel-vue-i18n";
import Empty from "@/Components/Empty.vue";
import Skeleton from 'primevue/skeleton';
import {usePage} from "@inertiajs/vue3";
import CreatePost from "@/Pages/Dashboard/Partials/CreatePost.vue";
import {usePermission} from "@/Composables/permissions.js";

const props = defineProps({
    postCounts: Number,
    authorName: String,
})

const posts = ref([]);
const loading = ref(false);
const { hasPermission } = usePermission();

const getResults = async () => {
    loading.value = true;

    try {
        let url = '/dashboard/getPosts';

        const response = await axios.get(url);
        posts.value = response.data;
    } catch (error) {
        console.error('Error changing locale:', error);
    } finally {
        loading.value = false;
    }
};

getResults();

const expandedPosts = ref([]);

const toggleExpand = (index) => {
    expandedPosts.value[index] = !expandedPosts.value[index];
};

const formatPostDate = (date) => {
    const now = dayjs();
    const postDate = dayjs(date);

    if (postDate.isSame(now, 'day')) {
        return postDate.format('HH:mm');
    } else if (postDate.isSame(now.subtract(1, 'day'), 'day')) {
        return wTrans('public.yesterday');
    } else {
        return postDate.format('ddd, DD MMM');
    }
};

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});
</script>

<template>
    <div
        class="flex flex-col gap-5 self-stretch p-4 md:py-6 md:px-8 bg-white rounded-2xl shadow-toast w-full h-[680px]"
    >
        <div class="flex justify-between items-center w-full">
            <span class="text-gray-950 font-bold">{{ $t('public.welcome_to_forum') }} 🌟</span>
            <CreatePost
                v-if="hasPermission('post_forum')"
                :authorName="authorName"
            />
        </div>
        <div
            v-if="postCounts === 0 && !posts.length"
            class="flex flex-col items-center justify-center self-stretch h-full"
        >
            <Empty
                :title="$t('public.no_posts_yet')"
                :message="$t('public.no_posts_yet_caption')"
            >
                <template #image>
                    <img src="/img/no_data/illustration-forum.svg" alt="no data" />
                </template>
            </Empty>
        </div>

        <div
            v-else-if="loading"
            class="py-4 flex flex-col gap-5 items-center self-stretch"
        >
            <div class="flex justify-between items-start self-stretch">
                <div class="flex items-center gap-3 w-full">
                    <div class="w-7 h-7 shrink-0 grow-0 rounded-full overflow-hidden">
                        <DefaultProfilePhoto />
                    </div>
                    <div class="flex flex-col items-start text-sm">
                        <Skeleton width="9rem" height="0.6rem" borderRadius="2rem"></Skeleton>
                    </div>
                </div>
                <Skeleton width="2rem" height="0.6rem" class="my-1" borderRadius="2rem"></Skeleton>
            </div>

            <!-- content -->
            <div class="flex flex-col gap-5 items-start self-stretch pl-10">
                <Skeleton width="10rem" height="4rem"></Skeleton>
                <div class="flex flex-col gap-3 items-start self-stretch text-sm text-gray-950">
                    <Skeleton width="9rem" height="0.6rem" borderRadius="2rem"></Skeleton>
                    <Skeleton width="9rem" height="0.6rem" borderRadius="2rem"></Skeleton>
                </div>
            </div>
        </div>

        <div v-else class="overflow-y-auto">
            <div
                v-for="post in posts"
                :key="post.id"
                class="border-b border-gray-200 last:border-transparent py-4 flex flex-col gap-5 items-center self-stretch"
            >
                <div class="flex justify-between items-start self-stretch">
                    <div class="flex items-center gap-3 w-full">
                        <div class="w-7 h-7 shrink-0 grow-0 rounded-full overflow-hidden">
                            <div v-if="post.display_avatar">
                                <img :src="post.display_avatar" alt="Profile Photo" />
                            </div>
                            <div v-else>
                                <DefaultProfilePhoto />
                            </div>
                        </div>
                        <span class="text-sm text-gray-950 font-bold">{{ post.display_name }}</span>
                    </div>
                    <span class="text-gray-700 text-xs text-right min-w-28">{{ formatPostDate(post.created_at) }}</span>
                </div>

                <!-- content -->
                <div class="flex flex-col gap-5 items-start self-stretch pl-10">
                    <Image
                        v-if="post.post_attachment"
                        :src="post.post_attachment"
                        alt="Image"
                        image-class="w-[250px] h-[160px] object-contain"
                        preview
                    />
                    <div class="flex flex-col gap-3 items-start self-stretch text-sm text-gray-950">
                        <span class="font-semibold">{{ post.subject }}</span>
                        <div
                            v-html="post.message"
                            :class="[
                            'prose prose-p:my-0 prose-ul:my-0 w-full',
                            {
                                 'max-h-[82px] truncate': !expandedPosts[post.id],
                                 'max-h-auto': expandedPosts[post.id],
                            }
                        ]"
                        />
                    </div>
                    <div
                        class="text-primary font-medium text-xs hover:text-primary-700 select-none cursor-pointer"
                        @click="toggleExpand(post.id)"
                    >
                        {{ expandedPosts[post.id] ? $t('public.see_less') : $t('public.see_more') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
