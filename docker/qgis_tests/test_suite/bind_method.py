def bind_method(class_target):
    def method_binder(func):
        setattr(class_target, func.__name__, func)

        return func

    return method_binder
