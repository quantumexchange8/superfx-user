export default {
    root: {
        class: [
            //Flex
            'flex flex-col',

            //Shape
            'rounded-[12px]',
            'shadow-md',

            //Color
            'bg-surface-0',
            'text-surface-700'
        ]
    },
    body: {
        class: [
            //Flex
            'flex flex-col',
            'gap-4',

            'p-6'
        ]
    },
    caption: {
        class: [
            //Flex
            'flex flex-col',
            'gap-2'
        ]
    },
    title: {
        class: 'text-xl font-semibold mb-0'
    },
    subtitle: {
        class: [
            //Font
            'font-normal',

            //Spacing
            'mb-0',

            //Color
            'text-surface-600'
        ]
    },
    content: {
        class: 'p-0'
    },
    footer: {
        class: 'p-0'
    }
};
