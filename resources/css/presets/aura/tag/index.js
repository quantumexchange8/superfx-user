export default {
    root: ({ props }) => ({
        class: [
            //Font
            'text-xs font-bold',

            //Alignments
            'inline-flex items-center justify-center',

            //Spacing
            'px-2 py-1',

            //Shape
            {
                'rounded-md': !props.rounded,
                'rounded-full': props.rounded
            },

            //Colors
            {
                'text-primary-highlight-inverse bg-primary-highlight': props.severity == null || props.severity == 'primary',
                'text-green-700 dark:text-green-300 bg-green-100 dark:bg-green-500/20': props.severity == 'success',
                'text-surface-700 dark:text-surface-300 bg-surface-100 dark:bg-surface-500/20': props.severity == 'secondary',
                'text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-500/20': props.severity == 'info',
                'text-warning-700 dark:text-warning-300 bg-warning-100 dark:bg-warning-500/20': props.severity == 'warning',
                'text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-500/20': props.severity == 'danger',
                'text-white bg-black': props.severity == 'contrast'
            }
        ]
    }),
    value: {
        class: 'leading-normal'
    },
    icon: {
        class: 'mr-1 text-sm'
    }
};
