package org.venity.jphp.ext.android.fx.classes;

import javafx.collections.ObservableList;
import javafx.geometry.Orientation;
import javafx.scene.Node;
import javafx.scene.control.SplitPane;
import org.venity.jphp.ext.android.AndroidExtension;
import org.venity.jphp.ext.android.fx.JavaFXExtension;
import php.runtime.annotation.Reflection;
import php.runtime.annotation.Reflection.Name;
import php.runtime.annotation.Reflection.Property;
import php.runtime.annotation.Reflection.Signature;
import php.runtime.env.Environment;
import php.runtime.reflection.ClassEntity;

import java.util.List;

@Name("UXSplitPane")
@Reflection.Namespace(AndroidExtension.NS_FX)
public class UXSplitPane extends UXControl<SplitPane> {
    interface WrappedInterface {
        @Property ObservableList<Node> items();
        @Property Orientation orientation();
        @Property double[] dividerPositions();

        void setDividerPosition(int dividerIndex, double position);
    }

    public UXSplitPane(Environment env, SplitPane wrappedObject) {
        super(env, wrappedObject);
    }

    public UXSplitPane(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }

    @Signature
    public void __construct() {
        __wrappedObject = new SplitPane();
    }

    @Signature
    public void __construct(List<Node> children) {
        __wrappedObject = new SplitPane(children.toArray(new Node[children.size()]));
    }

    @Signature
    public static void setResizeWithParent(Node node, boolean value) {
        SplitPane.setResizableWithParent(node, value);
    }
}