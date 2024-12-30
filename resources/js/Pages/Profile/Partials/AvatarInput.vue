<script setup>
import { IconCamera, IconX } from "@tabler/icons-vue";
import { ref, watch, onMounted } from "vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";

const props = defineProps({
    value: File,
    defaultSrc: String,
    removeImg: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:profile_pic']);

const src = ref(props.defaultSrc);
const selectedProfilePic = ref(null);
const profilePicRef = ref(null);

const handleProfilePic = (event) => {
    const file = event.target.files[0];

    if (file) {
        // Emit the file so it can be bound with v-model
        emit('update:profile_pic', file);

        // Display the selected image
        const reader = new FileReader();
        reader.onload = () => {
            selectedProfilePic.value = reader.result;
            src.value = selectedProfilePic.value;
        };
        reader.readAsDataURL(file);
    } else {
        selectedProfilePic.value = null;
        src.value = props.defaultSrc;
    }
};

const remove = () => {
    selectedProfilePic.value = null;
    src.value = props.defaultSrc;
    emit('update:profile_pic', null);
};

onMounted(() => {
    // Ensure the src is updated on mount
    if (props.value) {
        const reader = new FileReader();
        reader.onload = () => {
            selectedProfilePic.value = reader.result;
            src.value = selectedProfilePic.value;
        };
        reader.readAsDataURL(props.value);
    }
});

watch(() => props.defaultSrc, (newSrc) => {
    src.value = newSrc;
});

watch(() => props.removeImg, () => {
    if (props.removeImg) {
        src.value = null
    }
})
</script>

<template>
    <div class="relative inline-block overflow-hidden">
        <input
            type="file"
            accept="image/*"
            class="hidden"
            ref="profilePicRef"
            @change="handleProfilePic"
        />

        <div v-if="src">
            <img :src="src" alt="Avatar" class="h-full w-full object-cover" />
        </div>
        <div v-else>
            <DefaultProfilePhoto />
        </div>
        <div class="absolute top-0 h-full w-full bg-black bg-opacity-25 flex justify-center items-center space-x-2">
            <button
                @click="$refs.profilePicRef.click()"
                class="rounded-full text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-25 p-2 focus:outline-none transition duration-200"
            >
                <IconCamera size="20" stroke-width="1.25" />
            </button>
            <button
                v-if="selectedProfilePic"
                @click.prevent="remove"
                class="rounded-full text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-25 p-2 focus:outline-none transition duration-200"
            >
                <IconX size="20" stroke-width="1.25" />
            </button>
        </div>
    </div>
</template>
